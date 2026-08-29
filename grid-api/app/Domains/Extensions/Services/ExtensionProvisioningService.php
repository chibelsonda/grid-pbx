<?php

namespace App\Domains\Extensions\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Services\CallflowJsonNormalizer;
use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\Devices\Enums\DeviceRegistrationStatus;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Devices\Services\DeviceMutationDataFactory;
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
use App\Domains\Voicemail\Services\VoicemailMutationDataFactory;
use App\Shared\Switch\MetaflowPolicy;
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
        private readonly MetaflowPolicy $metaflowPolicy,
        private readonly DeviceMutationDataFactory $deviceMutationData,
        private readonly VoicemailMutationDataFactory $voicemailMutationData,
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
                $voicemailInput = [
                    ...$data['voicemail']['input'],
                    'name' => "({$data['extension']}) {$displayName}",
                    'mailbox' => $data['extension'],
                    'assigned_extension_id' => null,
                ];
                $created['voicemail_box'] = $this->gateway->createVoicemailBox(
                    $account,
                    $this->voicemailMutationData->make(
                        $account,
                        $voicemailInput,
                        $userResourceId,
                    ),
                );
                $this->recordCreatedResource($operation, $created, 'voicemail_box');
            }

            if ($data['device']['enabled']) {
                $currentStep = 'device';
                $created['device'] = $this->gateway->createDevice(
                    $account,
                    $this->deviceMutationData->make(
                        $account,
                        $data['device']['input'],
                        $userResourceId,
                    ),
                );
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
            $data = $this->prepareAdvancedSwitchData($account, $extension, $data);
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
                $voicemailInput = [
                    ...$data['voicemail']['input'],
                    'name' => "({$data['extension']}) {$displayName}",
                    'mailbox' => $data['extension'],
                    'assigned_extension_id' => null,
                ];
                $voicemailData = $this->voicemailMutationData->make(
                    $account,
                    $voicemailInput,
                    $extension->switch_resource_id,
                    $managedVoicemail,
                );
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prepareAdvancedSwitchData(
        SwitchAccount $account,
        SwitchExtension $extension,
        array $data,
    ): array {
        $data = $this->resolveCallerIdNumbers($account, $extension, $data);
        $data = $this->resolveMediaReference(
            $account,
            $extension,
            $data,
            'music_on_hold',
        );
        $data = $this->resolveMediaReference(
            $account,
            $extension,
            $data,
            'pronounced_name',
        );
        $data = $this->preserveUserAdvancedMetadata($extension, $data);

        if (! isset($data['metaflows']) || ! is_array($data['metaflows'])) {
            return $data;
        }

        $current = is_array($extension->switch_json['metaflows'] ?? null)
            ? $extension->switch_json['metaflows']
            : [];
        $preserved = array_diff_key(
            $current,
            array_flip(['binding_digit', 'digit_timeout', 'listen_on']),
        );
        $actions = is_array($data['metaflows']['actions'] ?? null)
            ? $data['metaflows']['actions']
            : [];
        $maps = $this->metaflowPolicy->merge($current, $actions, $account);
        $preserved['numbers'] = $maps['numbers'];
        $preserved['patterns'] = $maps['patterns'];
        $data['metaflows']['preserved_options'] = $preserved;
        unset($data['metaflows']['actions']);

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function resolveMediaReference(
        SwitchAccount $account,
        SwitchExtension $extension,
        array $data,
        string $field,
    ): array {
        if (! isset($data[$field]) || ! is_array($data[$field])) {
            return $data;
        }

        if (($data[$field]['preserve_media'] ?? false) === true) {
            $data[$field]['media_id'] = data_get(
                $extension->switch_json,
                "{$field}.media_id",
            );
        } else {
            $publicId = $data[$field]['media_id'] ?? null;
            $media = is_string($publicId) && $publicId !== ''
                ? $account->media()->where('id', $publicId)->first()
                : null;

            if (is_string($publicId) && $publicId !== '' && $media === null) {
                throw ValidationException::withMessages([
                    "{$field}.media_id" => 'Select media projected for this account.',
                ]);
            }

            $data[$field]['media_id'] = $media?->switch_resource_id;
        }

        unset($data[$field]['preserve_media']);

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function resolveCallerIdNumbers(
        SwitchAccount $account,
        SwitchExtension $extension,
        array $data,
    ): array {
        if (! isset($data['caller_id']) || ! is_array($data['caller_id'])) {
            return $data;
        }

        foreach (['external' => false, 'emergency' => true] as $scope => $requiresE911) {
            $selection = is_array($data['caller_id'][$scope] ?? null)
                ? $data['caller_id'][$scope]
                : [];

            if (($selection['preserve_number'] ?? false) === true) {
                $data['caller_id'][$scope]['number'] = data_get(
                    $extension->switch_json,
                    "caller_id.{$scope}.number",
                );
            } else {
                $publicId = $selection['phone_number_id'] ?? null;
                $phoneNumber = is_string($publicId) && $publicId !== ''
                    ? $account->phoneNumbers()->where('id', $publicId)->first()
                    : null;

                if (is_string($publicId) && $publicId !== '' && $phoneNumber === null) {
                    throw ValidationException::withMessages([
                        "caller_id.{$scope}.phone_number_id" => 'Select a phone number assigned to this account.',
                    ]);
                }

                if ($requiresE911 && $phoneNumber !== null && ! $phoneNumber->isE911Enabled()) {
                    throw ValidationException::withMessages([
                        'caller_id.emergency.phone_number_id' => 'Select a phone number with E911 enabled.',
                    ]);
                }

                $data['caller_id'][$scope]['number'] = $phoneNumber?->number;
            }

            unset(
                $data['caller_id'][$scope]['phone_number_id'],
                $data['caller_id'][$scope]['preserve_number'],
            );
        }

        return $data;
    }

    /**
     * Preserve unmodeled Switch properties and recording storage URLs without accepting them from
     * the browser. Redacted values are never sent back upstream.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preserveUserAdvancedMetadata(SwitchExtension $extension, array $data): array
    {
        $snapshot = is_array($extension->switch_json) ? $extension->switch_json : [];
        $data['advanced_preserved_options'] = $this->safePreservedOptions($snapshot, [
            'id',
            'first_name',
            'last_name',
            'enabled',
            'email',
            'timezone',
            'language',
            'presence_id',
            'username',
            'password',
            'require_password_update',
            'hotdesk',
            'call_waiting',
            'do_not_disturb',
            'contact_list',
            'caller_id_options',
            'caller_id',
            'call_forward',
            'call_restriction',
            'call_recording',
            'media',
            'music_on_hold',
            'ringtones',
            'dial_plan',
            'formatters',
            'profile',
            'pronounced_name',
            'metaflows',
        ]);

        if (isset($data['caller_id']) && is_array($data['caller_id'])) {
            $callerId = is_array($snapshot['caller_id'] ?? null) ? $snapshot['caller_id'] : [];
            $data['caller_id']['preserved_options'] = $this->safePreservedOptions(
                $callerId,
                ['internal', 'external', 'emergency'],
            );

            foreach (['internal', 'external', 'emergency'] as $scope) {
                $current = is_array($callerId[$scope] ?? null) ? $callerId[$scope] : [];
                $data['caller_id'][$scope]['preserved_options'] = $this->safePreservedOptions(
                    $current,
                    ['name', 'number'],
                );
            }
        }

        if (isset($data['call_forward']) && is_array($data['call_forward'])) {
            $callForward = is_array($snapshot['call_forward'] ?? null) ? $snapshot['call_forward'] : [];
            $data['call_forward']['preserved_options'] = $this->safePreservedOptions($callForward, [
                'enabled',
                'number',
                'direct_calls_only',
                'failover',
                'ignore_early_media',
                'keep_caller_id',
                'require_keypress',
                'substitute',
            ]);
        }

        if (isset($data['call_restriction']) && is_array($data['call_restriction'])) {
            $currentRestrictions = is_array($snapshot['call_restriction'] ?? null)
                ? $snapshot['call_restriction']
                : [];
            $data['call_restriction']['preserved_options'] = [];

            foreach ($data['call_restriction'] as $classification => $restriction) {
                if ($classification === 'preserved_options' || ! is_array($restriction)) {
                    continue;
                }

                $current = is_array($currentRestrictions[$classification] ?? null)
                    ? $currentRestrictions[$classification]
                    : [];
                $data['call_restriction']['preserved_options'][$classification] = $this
                    ->safePreservedOptions($current, ['action']);
            }
        }

        if (isset($data['call_recording']) && is_array($data['call_recording'])) {
            foreach (['any', 'inbound', 'outbound'] as $direction) {
                foreach (['any', 'onnet', 'offnet'] as $network) {
                    $path = "call_recording.{$direction}.{$network}";
                    $current = data_get($snapshot, $path);
                    $current = is_array($current) ? $current : [];
                    data_set(
                        $data,
                        "{$path}.preserved_options",
                        $this->safePreservedOptions($current, [
                            'enabled',
                            'format',
                            'record_min_sec',
                            'record_on_answer',
                            'record_on_bridge',
                            'record_sample_rate',
                            'time_limit',
                        ]),
                    );
                }
            }
        }

        if (isset($data['media']) && is_array($data['media'])) {
            $current = is_array($snapshot['media'] ?? null) ? $snapshot['media'] : [];
            $data['media']['preserved_options'] = $this->safePreservedOptions($current, [
                'audio',
                'video',
                'bypass_media',
                'encryption',
                'fax_option',
                'ignore_early_media',
                'progress_timeout',
            ]);

            foreach (['audio', 'video'] as $stream) {
                $currentStream = is_array($current[$stream] ?? null) ? $current[$stream] : [];
                $data['media']['preserved_options'][$stream] = $this->safePreservedOptions(
                    $currentStream,
                    ['codecs'],
                );
            }

            $currentEncryption = is_array($current['encryption'] ?? null)
                ? $current['encryption']
                : [];
            $data['media']['preserved_options']['encryption'] = $this->safePreservedOptions(
                $currentEncryption,
                ['enforce_security', 'methods'],
            );
        }

        if (isset($data['music_on_hold']) && is_array($data['music_on_hold'])) {
            $current = is_array($snapshot['music_on_hold'] ?? null)
                ? $snapshot['music_on_hold']
                : [];
            $data['music_on_hold']['preserved_options'] = $this->safePreservedOptions(
                $current,
                ['media_id'],
            );
        }

        if (isset($data['ringtones']) && is_array($data['ringtones'])) {
            $current = is_array($snapshot['ringtones'] ?? null) ? $snapshot['ringtones'] : [];
            $data['ringtones']['preserved_options'] = $this->safePreservedOptions(
                $current,
                ['internal', 'external'],
            );
        }

        if (isset($data['dial_plan']) && is_array($data['dial_plan'])) {
            $current = is_array($snapshot['dial_plan'] ?? null) ? $snapshot['dial_plan'] : [];

            foreach ($data['dial_plan']['rules'] ?? [] as $index => $rule) {
                if (! is_array($rule) || ! is_string($rule['pattern'] ?? null)) {
                    continue;
                }

                $currentRule = is_array($current[$rule['pattern']] ?? null)
                    ? $current[$rule['pattern']]
                    : [];
                $data['dial_plan']['rules'][$index]['preserved_options'] = $this
                    ->safePreservedOptions($currentRule, ['description', 'prefix', 'suffix']);
            }
        }

        if (isset($data['formatters']) && is_array($data['formatters'])) {
            $current = is_array($snapshot['formatters'] ?? null) ? $snapshot['formatters'] : [];
            $occurrences = [];

            foreach ($data['formatters'] as $index => $formatter) {
                $field = is_array($formatter) ? ($formatter['field'] ?? null) : null;
                if (! is_string($field)) {
                    continue;
                }

                $occurrence = $occurrences[$field] ?? 0;
                $occurrences[$field] = $occurrence + 1;
                $stored = $current[$field] ?? null;
                $storedRules = is_array($stored) && array_is_list($stored) ? $stored : [$stored];
                $currentRule = is_array($storedRules[$occurrence] ?? null)
                    ? $storedRules[$occurrence]
                    : [];
                $data['formatters'][$index]['preserved_options'] = $this->safePreservedOptions(
                    $currentRule,
                    [
                        'direction',
                        'match_invite_format',
                        'prefix',
                        'regex',
                        'strip',
                        'suffix',
                        'value',
                    ],
                );
            }
        }

        if (isset($data['profile']) && is_array($data['profile'])) {
            $current = is_array($snapshot['profile'] ?? null) ? $snapshot['profile'] : [];
            $data['profile']['preserved_options'] = $this->safePreservedOptions($current, [
                'addresses',
                'assistant',
                'birthday',
                'nicknames',
                'note',
                'role',
                'sort-string',
                'title',
            ]);
        }

        if (isset($data['pronounced_name']) && is_array($data['pronounced_name'])) {
            $current = is_array($snapshot['pronounced_name'] ?? null)
                ? $snapshot['pronounced_name']
                : [];
            $data['pronounced_name']['preserved_options'] = $this->safePreservedOptions(
                $current,
                ['media_id'],
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $editableKeys
     * @return array<string, mixed>
     */
    private function safePreservedOptions(array $source, array $editableKeys): array
    {
        $preserved = $this->withoutRedactedValues(
            array_diff_key($source, array_flip($editableKeys)),
        );

        return is_array($preserved) ? $preserved : [];
    }

    private function withoutRedactedValues(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value === '[REDACTED]' ? null : $value;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $item = $this->withoutRedactedValues($item);

            if ($item !== null) {
                $clean[$key] = $item;
            }
        }

        return $clean;
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
            && $data['voicemail']['input']['require_pin']
            && $managedVoicemail === null
            && empty($data['voicemail']['input']['pin'])) {
            $errors['voicemail.input.pin'][] = 'A PIN is required when creating a managed mailbox.';
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
            'sip_credentials_supplied' => isset($data['device']['input']['sip']['username'])
                || isset($data['device']['input']['sip']['password']),
        ];
    }
}
