<?php

namespace App\Domains\Extensions\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Services\CallflowJsonNormalizer;
use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\Devices\Enums\DeviceRegistrationStatus;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Extensions\Exceptions\ExtensionProvisioningException;
use App\Domains\Extensions\Exceptions\ExtensionUpdateException;
use App\Domains\Extensions\Models\ExtensionLifecycleOperation;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\LineKeys\Services\LineKeyProjectionService;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnexpectedValueException;

class ExtensionProvisioningService
{
    private const WORKFLOW = 'extension_provisioning';

    public function __construct(
        private readonly SwitchExtensionProvisioningGateway $gateway,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
        private readonly CallflowReferenceResolver $callflowReferences,
        private readonly CallflowJsonNormalizer $callflowJson,
        private readonly AuditService $audit,
        private readonly LineKeyProjectionService $lineKeyProjection,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchExtension {
        /** @var array<string, array<string, mixed>> $created */
        $created = [];
        $currentStep = 'user';
        $operation = $this->startLifecycleOperation($account, $actor, 'provision', null, [
            'extension' => $data['extension'],
            'display_name' => trim("{$data['first_name']} {$data['last_name']}"),
            'resource_ids' => [],
            'compensation_failures' => [],
        ]);

        try {
            $created['user'] = $this->gateway->createUser($account, $data);
            $this->recordCreatedResource($operation, $created, 'user');
            $userResourceId = $this->resourceId($created['user'], 'user');
            $displayName = trim("{$data['first_name']} {$data['last_name']}");

            if ($data['voicemail']['enabled']) {
                $currentStep = 'voicemail_box';
                $created['voicemail_box'] = $this->gateway->createVoicemailBox($account, [
                    'name' => "({$data['extension']}) {$displayName}",
                    'mailbox' => $data['extension'],
                    'owner_id' => $userResourceId,
                    'timezone' => $data['timezone'] ?? null,
                    'notification_emails' => $data['voicemail']['notification_emails'],
                    'transcribe' => $data['voicemail']['transcribe'],
                    'require_pin' => $data['voicemail']['require_pin'],
                    'pin' => $data['voicemail']['pin'] ?? null,
                ]);
                $this->recordCreatedResource($operation, $created, 'voicemail_box');
            }

            if ($data['device']['enabled']) {
                $currentStep = 'device';
                $created['device'] = $this->gateway->createDevice($account, [
                    'name' => $data['device']['name'],
                    'device_type' => $data['device']['device_type'],
                    'owner_id' => $userResourceId,
                    'make' => $data['device']['make'] ?? null,
                    'model' => $data['device']['model'] ?? null,
                    'mac_address' => $data['device']['mac_address'] ?? null,
                    'sip_username' => $data['device']['sip_username'] ?? null,
                    'sip_password' => $data['device']['sip_password'] ?? null,
                ]);
                $this->recordCreatedResource($operation, $created, 'device');
            }

            $currentStep = 'callflow';
            $created['callflow'] = $this->gateway->createManagedCallflow(
                $account,
                $displayName,
                $data['extension'],
                $userResourceId,
                isset($created['voicemail_box'])
                    ? $this->resourceId($created['voicemail_box'], 'voicemail box')
                    : null,
            );
            $this->recordCreatedResource($operation, $created, 'callflow');
            $currentStep = 'projection';

            return DB::transaction(function () use ($account, $actor, $data, $ipAddress, $created, $operation): SwitchExtension {
                $extension = $this->projectUser($account, $created['user']);

                if (isset($created['voicemail_box'])) {
                    $this->projectVoicemailBox($account, $extension, $created['voicemail_box']);
                }

                if (isset($created['device'])) {
                    $this->projectDevice($account, $extension, $created['device']);
                }

                $this->projectCallflow($account, $extension, $created['callflow']);
                $operation->forceFill([
                    'switch_extension_id' => $extension->getKey(),
                    'status' => 'succeeded',
                    'failed_step' => null,
                    'error_type' => null,
                    'error_message' => null,
                    'completed_at' => now(),
                ])->save();
                $this->audit->record(
                    $actor,
                    $account,
                    'extension.provisioned',
                    'succeeded',
                    $extension->switch_resource_id,
                    $this->safeMetadata($data, $extension),
                    $ipAddress,
                    'extension',
                );

                return $extension->load(['devices', 'voicemailBoxes', 'callflows']);
            });
        } catch (Throwable $exception) {
            $compensationFailures = $this->compensate($account, $created);
            $operation->forceFill([
                'status' => $compensationFailures === [] ? 'rolled_back' : 'failed',
                'failed_step' => $currentStep,
                'error_type' => $exception::class,
                'error_message' => $exception->getMessage(),
                'context' => array_replace($operation->context ?? [], [
                    'compensation_failures' => $compensationFailures,
                ]),
                'completed_at' => now(),
            ])->save();
            $workflowException = new ExtensionProvisioningException($operation->id, $compensationFailures, $exception);

            try {
                $this->audit->record(
                    $actor,
                    $account,
                    'extension.provision_failed',
                    'failed',
                    isset($created['user']) ? $this->optionalResourceId($created['user']) : null,
                    [
                        'extension' => $data['extension'],
                        'completed_steps' => array_keys($created),
                        'compensation_failures' => $compensationFailures,
                        'repair_required' => $workflowException->repairRequired(),
                        'error_type' => $exception::class,
                    ],
                    $ipAddress,
                    'extension',
                );
            } catch (Throwable) {
                // Preserve the provisioning failure if audit persistence is unavailable.
            }

            throw $workflowException;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        SwitchExtension $extension,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchExtension {
        $this->assertManagedUpdate($extension, $data);
        $managedCallflow = $extension->callflows()
            ->where('is_managed', true)
            ->where('managed_by_workflow', self::WORKFLOW)
            ->firstOrFail();
        $managedVoicemail = $extension->voicemailBoxes()
            ->where('is_managed', true)
            ->where('managed_by_workflow', self::WORKFLOW)
            ->first();
        $completedSteps = [];
        $snapshots = [];
        $createdVoicemail = false;
        $deletedVoicemail = false;
        $currentStep = 'user';
        $operation = $this->startLifecycleOperation($account, $actor, 'update', $extension, [
            'extension_id' => $extension->id,
            'previous_extension' => $extension->extension,
            'requested_extension' => $data['extension'],
            'display_name' => trim("{$data['first_name']} {$data['last_name']}"),
        ]);

        try {
            $snapshots['user'] = $this->gateway->updateUser(
                $account,
                $extension->switch_resource_id,
                $data,
            );
            $completedSteps[] = 'user';
            $this->recordLifecycleProgress($operation, $completedSteps);
            $displayName = trim("{$data['first_name']} {$data['last_name']}");

            if ($data['voicemail']['enabled']) {
                $currentStep = 'voicemail_box';
                $voicemailData = [
                    'name' => "({$data['extension']}) {$displayName}",
                    'mailbox' => $data['extension'],
                    'owner_id' => $extension->switch_resource_id,
                    'timezone' => $data['timezone'] ?? null,
                    'notification_emails' => $data['voicemail']['notification_emails'],
                    'transcribe' => $data['voicemail']['transcribe'],
                    'require_pin' => $data['voicemail']['require_pin'],
                    'pin' => $data['voicemail']['pin'] ?? null,
                ];
                $snapshots['voicemail_box'] = $managedVoicemail === null
                    ? $this->gateway->createVoicemailBox($account, $voicemailData)
                    : $this->gateway->updateVoicemailBox(
                        $account,
                        $managedVoicemail->switch_resource_id,
                        $voicemailData,
                    );
                $createdVoicemail = $managedVoicemail === null;
                $completedSteps[] = $createdVoicemail ? 'voicemail_box_created' : 'voicemail_box';
                $this->recordLifecycleProgress($operation, $completedSteps);
            }

            $voicemailResourceId = isset($snapshots['voicemail_box'])
                ? $this->resourceId($snapshots['voicemail_box'], 'voicemail box')
                : null;
            $currentStep = 'callflow';
            $snapshots['callflow'] = $this->gateway->updateManagedCallflow(
                $account,
                $managedCallflow->switch_resource_id,
                $extension->switch_resource_id,
                (string) $extension->extension,
                $data['extension'],
                $displayName,
                $voicemailResourceId,
            );
            $completedSteps[] = 'callflow';
            $this->recordLifecycleProgress($operation, $completedSteps);

            if (! $data['voicemail']['enabled'] && $managedVoicemail !== null) {
                $currentStep = 'voicemail_box_delete';
                $this->gateway->deleteVoicemailBox($account, $managedVoicemail->switch_resource_id);
                $deletedVoicemail = true;
                $completedSteps[] = 'voicemail_box_deleted';
                $this->recordLifecycleProgress($operation, $completedSteps);
            }

            $currentStep = 'projection';

            return DB::transaction(function () use (
                $account,
                $extension,
                $actor,
                $data,
                $ipAddress,
                $snapshots,
                $managedVoicemail,
                $deletedVoicemail,
                $operation,
            ): SwitchExtension {
                $projected = $this->projectUser($account, $snapshots['user']);

                if (isset($snapshots['voicemail_box'])) {
                    $this->projectVoicemailBox($account, $projected, $snapshots['voicemail_box']);
                } elseif ($deletedVoicemail && $managedVoicemail !== null) {
                    $managedVoicemail->delete();
                }

                $this->projectCallflow($account, $projected, $snapshots['callflow']);
                $operation->forceFill([
                    'status' => 'succeeded',
                    'failed_step' => null,
                    'error_type' => null,
                    'error_message' => null,
                    'completed_at' => now(),
                ])->save();
                $this->audit->record(
                    $actor,
                    $account,
                    'extension.updated',
                    'succeeded',
                    $projected->switch_resource_id,
                    [
                        'extension_id' => $projected->id,
                        'previous_extension' => $extension->extension,
                        'extension' => $data['extension'],
                        'voicemail_enabled' => $data['voicemail']['enabled'],
                    ],
                    $ipAddress,
                    'extension',
                );

                return $projected->fresh()->load(['devices', 'voicemailBoxes', 'callflows']);
            });
        } catch (Throwable $exception) {
            if ($createdVoicemail && isset($snapshots['voicemail_box'])) {
                try {
                    $this->gateway->deleteVoicemailBox(
                        $account,
                        $this->resourceId($snapshots['voicemail_box'], 'voicemail box'),
                    );
                    $completedSteps[] = 'voicemail_box_compensated';
                    $this->recordLifecycleProgress($operation, $completedSteps);
                } catch (Throwable) {
                    $completedSteps[] = 'voicemail_box_compensation_failed';
                    $this->recordLifecycleProgress($operation, $completedSteps);
                }
            }

            $extension->forceFill(['sync_status' => ProjectionStatus::Error])->save();
            $operation->forceFill([
                'status' => $completedSteps === [] ? 'rolled_back' : 'failed',
                'completed_steps' => $completedSteps,
                'failed_step' => $currentStep,
                'error_type' => $exception::class,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();
            $workflowException = new ExtensionUpdateException($operation->id, $completedSteps, $exception);

            try {
                $this->audit->record(
                    $actor,
                    $account,
                    'extension.update_failed',
                    'failed',
                    $extension->switch_resource_id,
                    [
                        'extension_id' => $extension->id,
                        'requested_extension' => $data['extension'],
                        'completed_steps' => $completedSteps,
                        'repair_required' => $workflowException->repairRequired(),
                        'error_type' => $exception::class,
                    ],
                    $ipAddress,
                    'extension',
                );
            } catch (Throwable) {
                // Preserve the upstream lifecycle failure.
            }

            throw $workflowException;
        }
    }

    /** @param array<string, mixed> $data */
    private function assertManagedUpdate(SwitchExtension $extension, array $data): void
    {
        $errors = [];

        if (! $extension->is_managed || $extension->managed_by_workflow !== self::WORKFLOW) {
            $errors['extension'][] = 'Only extensions created by the managed GridPBX workflow can be edited here.';
        }

        if ($extension->extension === null) {
            $errors['extension'][] = 'The managed extension has no projected extension number.';
        }

        $managedCallflowCount = $extension->callflows()
            ->where('is_managed', true)
            ->where('managed_by_workflow', self::WORKFLOW)
            ->count();

        if ($managedCallflowCount !== 1) {
            $errors['callflow'][] = 'The extension must have exactly one managed callflow before it can be edited.';
        }

        $managedVoicemail = $extension->voicemailBoxes()
            ->where('is_managed', true)
            ->where('managed_by_workflow', self::WORKFLOW)
            ->first();

        if (! $data['voicemail']['enabled'] && $managedVoicemail?->messages()->exists()) {
            $errors['voicemail.enabled'][] = 'Move or remove all voicemail messages before disabling the managed mailbox.';
        }

        if ($data['voicemail']['enabled']
            && $managedVoicemail === null
            && $extension->voicemailBoxes()->where('is_managed', false)->exists()) {
            $errors['voicemail.enabled'][] = 'This extension already has an independently managed mailbox.';
        }

        if ($data['voicemail']['enabled']
            && $data['voicemail']['require_pin']
            && $managedVoicemail === null
            && empty($data['voicemail']['pin'])) {
            $errors['voicemail.pin'][] = 'A PIN is required when creating a managed mailbox.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, array<string, mixed>> $created
     * @return list<string>
     */
    private function compensate(SwitchAccount $account, array $created): array
    {
        $failures = [];
        $steps = [
            'callflow' => fn (SwitchAccount $targetAccount, string $resourceId) => $this->gateway
                ->deleteCallflow($targetAccount, $resourceId),
            'device' => fn (SwitchAccount $targetAccount, string $resourceId) => $this->gateway
                ->deleteDevice($targetAccount, $resourceId),
            'voicemail_box' => fn (SwitchAccount $targetAccount, string $resourceId) => $this->gateway
                ->deleteVoicemailBox($targetAccount, $resourceId),
            'user' => fn (SwitchAccount $targetAccount, string $resourceId) => $this->gateway
                ->deleteUser($targetAccount, $resourceId),
        ];

        foreach ($steps as $name => $delete) {
            if (! isset($created[$name])) {
                continue;
            }

            try {
                $delete($account, $this->resourceId($created[$name], $name));
            } catch (Throwable) {
                $failures[] = $name;
            }
        }

        return $failures;
    }

    /** @param array<string, mixed> $snapshot */
    private function projectUser(SwitchAccount $account, array $snapshot): SwitchExtension
    {
        $firstName = $this->stringValue($snapshot['first_name'] ?? null);
        $lastName = $this->stringValue($snapshot['last_name'] ?? null);
        $username = $this->stringValue($snapshot['username'] ?? null);
        $fullName = trim(implode(' ', array_filter([$firstName, $lastName])));
        $extension = SwitchExtension::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $this->resourceId($snapshot, 'user'),
        ]);
        $extension->fill([
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $this->stringValue($snapshot['name'] ?? null)
                ?? ($fullName !== '' ? $fullName : ($username ?? 'Unnamed extension')),
            'email' => $this->stringValue($snapshot['email'] ?? null),
            'extension' => $this->stringValue(Arr::get($snapshot, 'caller_id.internal.number'))
                ?? $this->stringValue($snapshot['presence_id'] ?? null),
            'timezone' => $this->stringValue($snapshot['timezone'] ?? null),
            'is_enabled' => (bool) ($snapshot['enabled'] ?? true),
            'source_revision' => $this->stringValue($snapshot['_rev'] ?? null),
            'source_updated_at' => null,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $extension->exists ? $extension->projection_version + 1 : 1,
            'is_managed' => true,
            'managed_by_workflow' => self::WORKFLOW,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $extension->deleted_at = null;
        $extension->save();

        return $extension;
    }

    /** @param array<string, mixed> $snapshot */
    private function projectVoicemailBox(
        SwitchAccount $account,
        SwitchExtension $extension,
        array $snapshot,
    ): SwitchVoicemailBox {
        $voicemailBox = SwitchVoicemailBox::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $this->resourceId($snapshot, 'voicemail box'),
        ]);
        $voicemailBox->fill([
            'switch_extension_id' => $extension->getKey(),
            'owner_switch_resource_id' => $extension->switch_resource_id,
            'name' => $this->stringValue($snapshot['name'] ?? null),
            'mailbox' => $this->stringValue($snapshot['mailbox'] ?? null),
            'timezone' => $this->stringValue($snapshot['timezone'] ?? null),
            'notification_emails' => $this->stringList($snapshot['notify_email_addresses'] ?? null),
            'transcribe' => (bool) ($snapshot['transcribe'] ?? false),
            'require_pin' => (bool) ($snapshot['require_pin'] ?? false),
            'is_setup' => array_key_exists('is_setup', $snapshot) ? (bool) $snapshot['is_setup'] : null,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $voicemailBox->exists ? $voicemailBox->projection_version + 1 : 1,
            'is_managed' => true,
            'managed_by_workflow' => self::WORKFLOW,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $voicemailBox->deleted_at = null;
        $voicemailBox->save();

        return $voicemailBox;
    }

    /** @param array<string, mixed> $snapshot */
    private function projectDevice(
        SwitchAccount $account,
        SwitchExtension $extension,
        array $snapshot,
    ): SwitchDevice {
        $device = SwitchDevice::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $this->resourceId($snapshot, 'device'),
        ]);
        $device->fill([
            'switch_extension_id' => $extension->getKey(),
            'owner_switch_resource_id' => $extension->switch_resource_id,
            'name' => $this->stringValue($snapshot['name'] ?? null),
            'device_type' => $this->stringValue($snapshot['device_type'] ?? null),
            'make' => $this->stringValue($snapshot['make'] ?? Arr::get($snapshot, 'provision.endpoint_brand')),
            'endpoint_family' => $this->stringValue(Arr::get($snapshot, 'provision.endpoint_family')),
            'model' => $this->stringValue($snapshot['model'] ?? Arr::get($snapshot, 'provision.endpoint_model')),
            'mac_address' => $this->stringValue($snapshot['mac_address'] ?? Arr::get($snapshot, 'provision.mac_address')),
            'is_enabled' => (bool) ($snapshot['enabled'] ?? true),
            'registration_status' => DeviceRegistrationStatus::Unknown,
            'registration_checked_at' => null,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $device->exists ? $device->projection_version + 1 : 1,
            'is_managed' => true,
            'managed_by_workflow' => self::WORKFLOW,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $device->deleted_at = null;
        $device->save();
        $this->lineKeyProjection->project($device, $snapshot);

        return $device;
    }

    /** @param array<string, mixed> $data */
    private function projectCallflow(
        SwitchAccount $account,
        SwitchExtension $extension,
        array $data,
    ): SwitchCallflow {
        $snapshot = new CallflowSnapshot($data);
        $callflow = SwitchCallflow::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $snapshot->id,
        ]);
        $callflow->fill([
            'switch_extension_id' => $extension->getKey(),
            'owner_switch_resource_id' => $extension->switch_resource_id,
            'name' => $snapshot->name,
            'numbers' => $snapshot->numbers,
            'patterns' => $snapshot->patterns,
            'flags' => $snapshot->flags,
            'modules' => $snapshot->modules,
            'root_module' => $snapshot->flow?->module,
            'node_count' => $snapshot->nodeCount,
            'max_depth' => $snapshot->maxDepth,
            'is_feature_code' => false,
            'feature_code_name' => null,
            'feature_code_number' => null,
            'flow_structure' => ($flow = $this->callflowReferences->resolve(
                $account,
                is_array($snapshot->data['flow'] ?? null) ? $snapshot->data['flow'] : null,
            )) === null ? null : $this->callflowJson->flow($flow),
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $callflow->exists ? $callflow->projection_version + 1 : 1,
            'is_managed' => true,
            'managed_by_workflow' => self::WORKFLOW,
            'switch_json' => $this->callflowJson->document(
                $this->redactSensitiveData->handle($snapshot->toArray()),
            ),
        ]);
        $callflow->deleted_at = null;
        $callflow->save();

        return $callflow;
    }

    /** @param array<string, mixed> $snapshot */
    private function resourceId(array $snapshot, string $resource): string
    {
        $resourceId = $this->optionalResourceId($snapshot);

        if ($resourceId === null) {
            throw new UnexpectedValueException("Switch {$resource} response is missing its resource identifier.");
        }

        return $resourceId;
    }

    /** @param array<string, mixed> $snapshot */
    private function optionalResourceId(array $snapshot): ?string
    {
        return $this->stringValue($snapshot['id'] ?? null);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? $value : [],
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    /** @param array<string, mixed> $context */
    private function startLifecycleOperation(
        SwitchAccount $account,
        User $actor,
        string $operation,
        ?SwitchExtension $extension,
        array $context,
    ): ExtensionLifecycleOperation {
        return ExtensionLifecycleOperation::query()->create([
            'switch_account_id' => $account->getKey(),
            'switch_extension_id' => $extension?->getKey(),
            'requested_by_user_id' => $actor->getKey(),
            'operation' => $operation,
            'status' => 'running',
            'completed_steps' => [],
            'context' => $context,
        ]);
    }

    /** @param array<string, array<string, mixed>> $created */
    private function recordCreatedResource(
        ExtensionLifecycleOperation $operation,
        array $created,
        string $resource,
    ): void {
        $context = $operation->context ?? [];
        $resourceIds = is_array($context['resource_ids'] ?? null) ? $context['resource_ids'] : [];
        $resourceIds[$resource] = $this->resourceId($created[$resource], $resource);
        $context['resource_ids'] = $resourceIds;
        $operation->forceFill([
            'completed_steps' => array_keys($created),
            'context' => $context,
        ])->save();
    }

    /** @param list<string> $completedSteps */
    private function recordLifecycleProgress(
        ExtensionLifecycleOperation $operation,
        array $completedSteps,
    ): void {
        $operation->forceFill(['completed_steps' => $completedSteps])->save();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function safeMetadata(array $data, SwitchExtension $extension): array
    {
        return [
            'extension_id' => $extension->id,
            'extension' => $data['extension'],
            'voicemail_created' => $data['voicemail']['enabled'],
            'device_created' => $data['device']['enabled'],
            'sip_credentials_supplied' => isset($data['device']['sip_username'])
                || isset($data['device']['sip_password']),
        ];
    }
}
