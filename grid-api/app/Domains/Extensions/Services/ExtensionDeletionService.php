<?php

namespace App\Domains\Extensions\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Extensions\Exceptions\ExtensionDeletionException;
use App\Domains\Extensions\Models\ExtensionLifecycleOperation;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExtensionDeletionService
{
    private const WORKFLOW = 'extension_provisioning';

    public function __construct(
        private readonly SwitchExtensionProvisioningGateway $gateway,
        private readonly ExtensionDeletionPreviewService $preview,
        private readonly AuditService $audit,
    ) {}

    public function delete(
        SwitchAccount $account,
        SwitchExtension $extension,
        User $actor,
        string $confirmation,
        ?string $ipAddress = null,
    ): void {
        if (! hash_equals((string) $extension->extension, trim($confirmation))) {
            throw ValidationException::withMessages([
                'confirmation' => 'Enter the exact extension number to confirm deletion.',
            ]);
        }

        $preview = $this->preview->preview($account, $extension);

        if (! $preview['can_delete']) {
            throw ValidationException::withMessages([
                'extension' => collect($preview['blockers'])->pluck('message')->all(),
            ]);
        }

        $operation = $this->resumableOperation($account, $extension, $actor);
        $completed = array_values(array_filter(
            $operation->completed_steps ?? [],
            static fn (mixed $step): bool => is_string($step),
        ));
        $currentStep = null;

        try {
            $callflows = $extension->callflows()
                ->where('is_managed', true)
                ->where('managed_by_workflow', self::WORKFLOW)
                ->orderBy('callflow_id')
                ->get();
            $devices = $extension->devices()
                ->where('is_managed', true)
                ->where('managed_by_workflow', self::WORKFLOW)
                ->orderBy('device_id')
                ->get();
            $voicemailBoxes = $extension->voicemailBoxes()
                ->where('is_managed', true)
                ->where('managed_by_workflow', self::WORKFLOW)
                ->orderBy('voicemail_box_id')
                ->get();

            foreach ($callflows as $callflow) {
                $currentStep = "callflow:{$callflow->id}";
                $this->runStep($operation, $completed, $currentStep, fn () => $this->gateway
                    ->deleteCallflow($account, $callflow->switch_resource_id));
            }

            foreach ($devices as $device) {
                $currentStep = "device:{$device->id}";
                $this->runStep($operation, $completed, $currentStep, fn () => $this->gateway
                    ->deleteDevice($account, $device->switch_resource_id));
            }

            foreach ($voicemailBoxes as $voicemailBox) {
                $currentStep = "voicemail_box:{$voicemailBox->id}";
                $this->runStep($operation, $completed, $currentStep, fn () => $this->gateway
                    ->deleteVoicemailBox($account, $voicemailBox->switch_resource_id));
            }

            $currentStep = 'user';
            $this->runStep($operation, $completed, $currentStep, fn () => $this->gateway
                ->deleteUser($account, $extension->switch_resource_id));
            $currentStep = 'projection';

            DB::transaction(function () use (
                $account,
                $extension,
                $actor,
                $operation,
                $completed,
                $callflows,
                $devices,
                $voicemailBoxes,
                $ipAddress,
            ): void {
                $callflows->each->delete();
                $devices->each->delete();
                $voicemailBoxes->each->delete();
                $extension->delete();
                $operation->forceFill([
                    'status' => 'succeeded',
                    'completed_steps' => $completed,
                    'failed_step' => null,
                    'error_type' => null,
                    'error_message' => null,
                    'completed_at' => now(),
                ])->save();
                $this->audit->record(
                    $actor,
                    $account,
                    'extension.deleted',
                    'succeeded',
                    $extension->switch_resource_id,
                    [
                        'extension_id' => $extension->id,
                        'extension' => $extension->extension,
                        'operation_id' => $operation->id,
                        'completed_steps' => $this->stepTypes($completed),
                    ],
                    $ipAddress,
                    'extension',
                );
            });
        } catch (Throwable $exception) {
            $operation->forceFill([
                'status' => 'failed',
                'completed_steps' => $completed,
                'failed_step' => $currentStep,
                'error_type' => $exception::class,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();
            $extension->forceFill(['sync_status' => ProjectionStatus::Error])->save();
            $workflowException = new ExtensionDeletionException($operation->id, $completed, $exception);

            try {
                $this->audit->record(
                    $actor,
                    $account,
                    'extension.delete_failed',
                    'failed',
                    $extension->switch_resource_id,
                    [
                        'extension_id' => $extension->id,
                        'operation_id' => $operation->id,
                        'completed_steps' => $this->stepTypes($completed),
                        'failed_step' => $currentStep === null ? null : strtok($currentStep, ':'),
                        'repair_required' => $workflowException->repairRequired(),
                        'error_type' => $exception::class,
                    ],
                    $ipAddress,
                    'extension',
                );
            } catch (Throwable) {
                // Preserve the lifecycle failure if audit persistence is unavailable.
            }

            throw $workflowException;
        }
    }

    private function resumableOperation(
        SwitchAccount $account,
        SwitchExtension $extension,
        User $actor,
    ): ExtensionLifecycleOperation {
        return DB::transaction(function () use ($account, $extension, $actor): ExtensionLifecycleOperation {
            SwitchExtension::query()->whereKey($extension->getKey())->lockForUpdate()->firstOrFail();
            $operation = ExtensionLifecycleOperation::query()
                ->where('switch_account_id', $account->getKey())
                ->where('switch_extension_id', $extension->getKey())
                ->where('operation', 'delete')
                ->whereIn('status', ['running', 'failed'])
                ->latest('created_at')
                ->first();

            if ($operation?->status === 'running'
                && $operation->updated_at !== null
                && $operation->updated_at->isAfter(now()->subMinutes(15))) {
                throw ValidationException::withMessages([
                    'extension' => 'A managed deletion is already running for this extension.',
                ]);
            }

            if ($operation === null) {
                return ExtensionLifecycleOperation::query()->create([
                    'switch_account_id' => $account->getKey(),
                    'switch_extension_id' => $extension->getKey(),
                    'requested_by_user_id' => $actor->getKey(),
                    'operation' => 'delete',
                    'status' => 'running',
                    'completed_steps' => [],
                    'context' => [
                        'extension_id' => $extension->id,
                        'extension' => $extension->extension,
                    ],
                ]);
            }

            $operation->forceFill([
                'requested_by_user_id' => $actor->getKey(),
                'status' => 'running',
                'failed_step' => null,
                'error_type' => null,
                'error_message' => null,
                'completed_at' => null,
            ])->save();

            return $operation;
        });
    }

    /** @param list<string> $completed */
    private function runStep(
        ExtensionLifecycleOperation $operation,
        array &$completed,
        string $step,
        callable $callback,
    ): void {
        if (in_array($step, $completed, true)) {
            return;
        }

        $callback();
        $completed[] = $step;
        $operation->forceFill(['completed_steps' => $completed])->save();
    }

    /** @param list<string> $steps
     * @return list<string>
     */
    private function stepTypes(array $steps): array
    {
        return array_values(array_unique(array_map(
            static fn (string $step): string => strtok($step, ':'),
            $steps,
        )));
    }
}
