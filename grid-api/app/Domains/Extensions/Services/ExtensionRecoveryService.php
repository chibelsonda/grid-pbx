<?php

namespace App\Domains\Extensions\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Extensions\Exceptions\ExtensionRecoveryException;
use App\Domains\Extensions\Models\ExtensionLifecycleOperation;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Services\ExtensionSynchronizationService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExtensionRecoveryService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly Container $container,
    ) {}

    public function recover(
        SwitchAccount $account,
        ExtensionLifecycleOperation $operation,
        User $actor,
        ?string $confirmation = null,
        ?string $ipAddress = null,
    ): ExtensionLifecycleOperation {
        $operation = $this->claim($account, $operation, $actor);

        try {
            match ($operation->operation) {
                'provision' => $this->cleanupProvisioning($account, $operation),
                'update' => $this->reconcileUpdate($account, $operation, $actor),
                'delete' => $this->resumeDeletion($account, $operation, $actor, $confirmation, $ipAddress),
                default => throw ValidationException::withMessages([
                    'operation' => 'This lifecycle operation has no supported recovery action.',
                ]),
            };

            $operation->refresh()->load('extension');

            if ($operation->operation !== 'delete') {
                $operation->forceFill([
                    'status' => 'recovered',
                    'failed_step' => null,
                    'error_type' => null,
                    'error_message' => null,
                    'completed_at' => now(),
                ])->save();
                $this->audit->record(
                    $actor,
                    $account,
                    'extension.recovered',
                    'succeeded',
                    $operation->extension?->switch_resource_id,
                    [
                        'operation_id' => $operation->id,
                        'operation' => $operation->operation,
                        'recovery_action' => $this->recoveryAction($operation),
                    ],
                    $ipAddress,
                    'extension_lifecycle_operation',
                );
            }

            return $operation->fresh('extension');
        } catch (ValidationException $exception) {
            if ($operation->operation !== 'delete' && $operation->status === 'running') {
                $operation->forceFill([
                    'status' => 'failed',
                    'error_type' => $exception::class,
                    'error_message' => $exception->getMessage(),
                    'completed_at' => now(),
                ])->save();
            }

            throw $exception;
        } catch (Throwable $exception) {
            $operation->forceFill([
                'status' => 'failed',
                'error_type' => $exception::class,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            throw new ExtensionRecoveryException($operation->id, $exception);
        }
    }

    private function claim(
        SwitchAccount $account,
        ExtensionLifecycleOperation $operation,
        User $actor,
    ): ExtensionLifecycleOperation {
        return DB::transaction(function () use ($account, $operation, $actor): ExtensionLifecycleOperation {
            $claimed = ExtensionLifecycleOperation::query()
                ->where('switch_account_id', $account->getKey())
                ->whereKey($operation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($claimed->status, ['failed', 'running'], true)) {
                throw ValidationException::withMessages([
                    'operation' => 'Only failed or stale lifecycle operations can be recovered.',
                ]);
            }

            if ($claimed->status === 'running'
                && $claimed->updated_at?->isAfter(now()->subMinutes(15))) {
                throw ValidationException::withMessages([
                    'operation' => 'This lifecycle operation is still running.',
                ]);
            }

            if ($claimed->operation !== 'delete') {
                $claimed->forceFill([
                    'requested_by_user_id' => $actor->getKey(),
                    'status' => 'running',
                    'completed_at' => null,
                ])->save();
            }

            return $claimed->load('extension');
        });
    }

    private function cleanupProvisioning(
        SwitchAccount $account,
        ExtensionLifecycleOperation $operation,
    ): void {
        $context = $operation->context ?? [];
        $resourceIds = is_array($context['resource_ids'] ?? null) ? $context['resource_ids'] : [];
        $failures = array_values(array_filter(
            is_array($context['compensation_failures'] ?? null) ? $context['compensation_failures'] : [],
            static fn (mixed $step): bool => is_string($step),
        ));
        $gateway = $this->container->make(SwitchExtensionProvisioningGateway::class);
        $deletes = [
            'callflow' => fn (string $resourceId) => $gateway->deleteCallflow($account, $resourceId),
            'device' => fn (string $resourceId) => $gateway->deleteDevice($account, $resourceId),
            'voicemail_box' => fn (string $resourceId) => $gateway->deleteVoicemailBox($account, $resourceId),
            'user' => fn (string $resourceId) => $gateway->deleteUser($account, $resourceId),
        ];

        foreach ($deletes as $step => $delete) {
            if (! in_array($step, $failures, true)) {
                continue;
            }

            $resourceId = $resourceIds[$step] ?? null;

            if (! is_string($resourceId) || $resourceId === '') {
                throw new \UnexpectedValueException("Recovery is missing the {$step} resource identifier.");
            }

            $delete($resourceId);
            $failures = array_values(array_diff($failures, [$step]));
            $context['compensation_failures'] = $failures;
            $operation->forceFill(['context' => $context])->save();
        }
    }

    private function reconcileUpdate(
        SwitchAccount $account,
        ExtensionLifecycleOperation $operation,
        User $actor,
    ): void {
        if ($operation->extension === null) {
            throw ValidationException::withMessages([
                'operation' => 'The extension projection no longer exists. Run an account synchronization instead.',
            ]);
        }

        $run = $account->syncRuns()->create([
            'requested_by_user_id' => $actor->getKey(),
            'resource_type' => 'extensions',
            'status' => SyncRunStatus::Queued,
        ]);
        $this->container->make(ExtensionSynchronizationService::class)->handle($run);
    }

    private function resumeDeletion(
        SwitchAccount $account,
        ExtensionLifecycleOperation $operation,
        User $actor,
        ?string $confirmation,
        ?string $ipAddress,
    ): void {
        $extension = $operation->extension;

        if ($extension === null) {
            throw ValidationException::withMessages([
                'operation' => 'The extension projection no longer exists.',
            ]);
        }

        $this->container->make(ExtensionDeletionService::class)->delete(
            $account,
            $extension,
            $actor,
            (string) $confirmation,
            $ipAddress,
        );
    }

    private function recoveryAction(ExtensionLifecycleOperation $operation): string
    {
        return match ($operation->operation) {
            'provision' => 'cleanup',
            'update' => 'reconcile',
            'delete' => 'resume',
            default => 'unsupported',
        };
    }
}
