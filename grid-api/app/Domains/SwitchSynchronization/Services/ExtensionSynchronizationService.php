<?php

namespace App\Domains\SwitchSynchronization\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Services\CallflowReferenceResolver;
use App\Domains\Devices\Enums\DeviceRegistrationStatus;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\LineKeys\Services\LineKeyProjectionService;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Contracts\SwitchExtensionGateway;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use App\Domains\Voicemail\Services\VoicemailGreetingProjectionService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use GridPbx\Switch\Dto\Callflows\CallflowSnapshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ExtensionSynchronizationService
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;

    public function __construct(
        private readonly SwitchExtensionGateway $gateway,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
        private readonly VoicemailGreetingProjectionService $voicemailGreetingProjection,
        private readonly CallflowReferenceResolver $callflowReferences,
        private readonly LineKeyProjectionService $lineKeyProjection,
    ) {}

    public function handle(SyncRun $run): void
    {
        $run->update([
            'status' => SyncRunStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);

        $account = $run->switchAccount()->firstOrFail();
        $extensionRecords = [];

        foreach ($this->gateway->users($account) as $user) {
            $resourceId = $this->stringValue($user['id'] ?? null);

            if ($resourceId === null) {
                continue;
            }

            $extensionRecords[$resourceId] = $this->mapUser($user);
        }

        $deviceRecords = $this->mapResources($this->gateway->devices($account), $this->mapDevice(...));
        $deviceStatuses = $this->gateway->deviceStatuses($account);
        $voicemailRecords = $this->mapResources($this->gateway->voicemailBoxes($account), $this->mapVoicemailBox(...));
        $voicemailMessageRecords = [];
        $voicemailGreetingRecords = [];

        foreach ($voicemailRecords as $voicemailBoxResourceId => $voicemailRecord) {
            $voicemailMessageRecords[$voicemailBoxResourceId] = $this->mapVoicemailMessages(
                $this->gateway->voicemailMessages($account, $voicemailBoxResourceId),
            );
            $greetingResourceId = Arr::get($voicemailRecord, 'switch_json.media.unavailable');

            if (is_string($greetingResourceId) && $greetingResourceId !== '') {
                $voicemailGreetingRecords[$voicemailBoxResourceId] = $this->gateway->media(
                    $account,
                    $greetingResourceId,
                );
            }
        }

        $callflowRecords = $this->mapCallflows($this->gateway->callflows($account), $extensionRecords);

        DB::transaction(function () use ($run, $account, $extensionRecords, $deviceRecords, $deviceStatuses, $voicemailRecords, $voicemailMessageRecords, $voicemailGreetingRecords, $callflowRecords): void {
            $syncedAt = now();
            $deletedCount = 0;
            $deletedCount += $this->synchronizeProjection(SwitchExtension::class, $account->getKey(), $extensionRecords, $syncedAt);
            $extensionIdsByResource = SwitchExtension::query()
                ->where('switch_account_id', $account->getKey())
                ->pluck('extension_id', 'switch_resource_id')
                ->all();
            $devices = $this->associateExtensions($deviceRecords, $extensionIdsByResource);

            foreach ($devices as $resourceId => &$device) {
                $device['registration_status'] = ($deviceStatuses[$resourceId] ?? false)
                    ? DeviceRegistrationStatus::Registered
                    : DeviceRegistrationStatus::Unregistered;
                $device['registration_checked_at'] = $syncedAt;
            }
            unset($device);

            $deletedCount += $this->synchronizeProjection(SwitchDevice::class, $account->getKey(), $devices, $syncedAt);
            SwitchDevice::query()
                ->where('switch_account_id', $account->getKey())
                ->get()
                ->each(function (SwitchDevice $device): void {
                    if (is_array($device->switch_json)) {
                        $this->lineKeyProjection->project($device, $device->switch_json);
                    }
                });
            $deletedCount += $this->synchronizeProjection(SwitchVoicemailBox::class, $account->getKey(), $this->associateExtensions($voicemailRecords, $extensionIdsByResource), $syncedAt);
            $voicemailBoxIdsByResource = SwitchVoicemailBox::query()
                ->where('switch_account_id', $account->getKey())
                ->pluck('voicemail_box_id', 'switch_resource_id')
                ->all();
            $deletedCount += $this->synchronizeVoicemailMessages(
                $account->getKey(),
                $voicemailMessageRecords,
                $voicemailBoxIdsByResource,
                $syncedAt,
            );
            $deletedCount += $this->synchronizeVoicemailGreetings(
                $account,
                $voicemailGreetingRecords,
                $syncedAt,
            );
            $deletedCount += $this->synchronizeProjection(SwitchCallflow::class, $account->getKey(), $this->associateExtensions($callflowRecords, $extensionIdsByResource), $syncedAt);
            $this->callflowReferences->refresh($account);
            $voicemailMessageCount = array_sum(array_map('count', $voicemailMessageRecords));
            $processedCount = count($extensionRecords) + count($deviceRecords) + count($voicemailRecords) + $voicemailMessageCount + count($voicemailGreetingRecords) + count($callflowRecords);

            $run->update([
                'status' => SyncRunStatus::Succeeded,
                'processed_count' => $processedCount,
                'upserted_count' => $processedCount,
                'deleted_count' => $deletedCount,
                'finished_at' => now(),
            ]);

            SyncCheckpoint::query()->updateOrCreate([
                'switch_account_id' => $account->getKey(),
                'resource_type' => 'extensions',
            ], [
                'last_sync_run_id' => $run->getKey(),
                'cursor' => null,
                'status' => ProjectionStatus::Healthy,
                'last_successful_at' => now(),
                'error_message' => null,
            ]);
        });
    }

    /**
     * @template TModel of SwitchExtension|SwitchDevice|SwitchVoicemailBox|SwitchCallflow
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, array<string, mixed>>  $records
     */
    private function synchronizeProjection(string $modelClass, string $accountId, array $records, DateTimeInterface $syncedAt): int
    {
        foreach ($records as $resourceId => $attributes) {
            $projection = $modelClass::withTrashed()->firstOrNew([
                'switch_account_id' => $accountId,
                'switch_resource_id' => $resourceId,
            ]);
            $projection->fill($attributes + [
                'last_synced_at' => $syncedAt,
                'sync_status' => ProjectionStatus::Healthy,
                'projection_version' => 1,
            ]);
            $projection->deleted_at = null;
            $projection->save();
        }

        $resourceIds = array_keys($records);
        $missing = $modelClass::query()
            ->where('switch_account_id', $accountId)
            ->when($resourceIds !== [], fn ($query) => $query->whereNotIn('switch_resource_id', $resourceIds))
            ->get();

        $modelClass::destroy($missing->modelKeys());

        return $missing->count();
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $recordsByVoicemailBox
     * @param  array<string, string>  $voicemailBoxIdsByResource
     */
    private function synchronizeVoicemailMessages(
        string $accountId,
        array $recordsByVoicemailBox,
        array $voicemailBoxIdsByResource,
        DateTimeInterface $syncedAt,
    ): int {
        $resourceIds = [];

        foreach ($recordsByVoicemailBox as $voicemailBoxResourceId => $records) {
            $voicemailBoxId = $voicemailBoxIdsByResource[$voicemailBoxResourceId] ?? null;

            if ($voicemailBoxId === null) {
                continue;
            }

            foreach ($records as $resourceId => $attributes) {
                $resourceIds[] = $resourceId;
                $projection = SwitchVoicemailMessage::withTrashed()->firstOrNew([
                    'switch_account_id' => $accountId,
                    'switch_resource_id' => $resourceId,
                ]);
                $projection->fill($attributes + [
                    'switch_voicemail_box_id' => $voicemailBoxId,
                    'last_synced_at' => $syncedAt,
                    'sync_status' => ProjectionStatus::Healthy,
                    'projection_version' => 1,
                ]);
                $projection->deleted_at = null;
                $projection->save();
            }
        }

        $missing = SwitchVoicemailMessage::query()
            ->where('switch_account_id', $accountId)
            ->when($resourceIds !== [], fn ($query) => $query->whereNotIn('switch_resource_id', $resourceIds))
            ->get();
        SwitchVoicemailMessage::destroy($missing->modelKeys());

        return $missing->count();
    }

    /** @param array<string, array<string, mixed>> $recordsByVoicemailBox */
    private function synchronizeVoicemailGreetings(
        SwitchAccount $account,
        array $recordsByVoicemailBox,
        DateTimeInterface $syncedAt,
    ): int {
        $voicemailBoxes = SwitchVoicemailBox::query()
            ->where('switch_account_id', $account->getKey())
            ->whereIn('switch_resource_id', array_keys($recordsByVoicemailBox))
            ->get()
            ->keyBy('switch_resource_id');
        $projectedIds = [];

        foreach ($recordsByVoicemailBox as $voicemailBoxResourceId => $snapshot) {
            $voicemailBox = $voicemailBoxes->get($voicemailBoxResourceId);

            if ($voicemailBox !== null) {
                $greeting = $this->voicemailGreetingProjection->project($account, $voicemailBox, $snapshot);
                $greeting->update(['last_synced_at' => $syncedAt]);
                $projectedIds[] = $greeting->getKey();
            }
        }

        $missing = SwitchVoicemailGreeting::query()
            ->where('switch_account_id', $account->getKey())
            ->when($projectedIds !== [], fn ($query) => $query->whereNotIn('voicemail_greeting_id', $projectedIds))
            ->get();
        SwitchVoicemailGreeting::destroy($missing->modelKeys());

        return $missing->count();
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $resources
     * @param  callable(array<string, mixed>): array<string, mixed>  $mapper
     * @return array<string, array<string, mixed>>
     */
    private function mapResources(iterable $resources, callable $mapper): array
    {
        $records = [];

        foreach ($resources as $resource) {
            $resourceId = $this->stringValue($resource['id'] ?? null);

            if ($resourceId !== null) {
                $records[$resourceId] = $mapper($resource);
            }
        }

        return $records;
    }

    /**
     * @param  array<string, array<string, mixed>>  $records
     * @param  array<string, string>  $extensionIdsByResource
     * @return array<string, array<string, mixed>>
     */
    private function associateExtensions(array $records, array $extensionIdsByResource): array
    {
        foreach ($records as &$record) {
            $ownerResourceId = $record['owner_switch_resource_id'] ?? null;
            $record['switch_extension_id'] = is_string($ownerResourceId)
                ? ($extensionIdsByResource[$ownerResourceId] ?? null)
                : null;
        }
        unset($record);

        return $records;
    }

    /** @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function mapUser(array $user): array
    {
        $firstName = $this->stringValue($user['first_name'] ?? null);
        $lastName = $this->stringValue($user['last_name'] ?? null);
        $username = $this->stringValue($user['username'] ?? null);
        $name = $this->stringValue($user['name'] ?? null);
        $fullName = trim(implode(' ', array_filter([$firstName, $lastName])));
        $presenceId = $this->stringValue($user['presence_id'] ?? null);

        return [
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $name ?? ($fullName !== '' ? $fullName : ($username ?? 'Unnamed extension')),
            'email' => $this->stringValue($user['email'] ?? null),
            'extension' => $this->stringValue(Arr::get($user, 'caller_id.internal.number')) ?? $presenceId,
            'timezone' => $this->stringValue($user['timezone'] ?? null),
            'is_enabled' => (bool) ($user['enabled'] ?? true),
            'source_revision' => $this->stringValue($user['_rev'] ?? null),
            'source_updated_at' => null,
            'switch_json' => $this->redactSensitiveData->handle($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    private function mapDevice(array $device): array
    {
        return [
            'owner_switch_resource_id' => $this->stringValue($device['owner_id'] ?? null),
            'name' => $this->stringValue($device['name'] ?? null),
            'device_type' => $this->stringValue($device['device_type'] ?? null),
            'make' => $this->stringValue($device['make'] ?? Arr::get($device, 'provision.endpoint_brand')),
            'endpoint_family' => $this->stringValue(Arr::get($device, 'provision.endpoint_family')),
            'model' => $this->stringValue($device['model'] ?? Arr::get($device, 'provision.endpoint_model')),
            'mac_address' => $this->stringValue($device['mac_address'] ?? Arr::get($device, 'provision.mac_address')),
            'is_enabled' => (bool) ($device['enabled'] ?? true),
            'switch_json' => $this->redactSensitiveData->handle($device),
        ];
    }

    /**
     * @param  array<string, mixed>  $voicemailBox
     * @return array<string, mixed>
     */
    private function mapVoicemailBox(array $voicemailBox): array
    {
        return [
            'owner_switch_resource_id' => $this->stringValue($voicemailBox['owner_id'] ?? null),
            'name' => $this->stringValue($voicemailBox['name'] ?? null),
            'mailbox' => $this->stringValue($voicemailBox['mailbox'] ?? null),
            'timezone' => $this->stringValue($voicemailBox['timezone'] ?? null),
            'notification_emails' => array_values(array_filter(
                is_array($voicemailBox['notify_email_addresses'] ?? null) ? $voicemailBox['notify_email_addresses'] : [],
                static fn (mixed $email): bool => is_string($email) && $email !== '',
            )),
            'transcribe' => (bool) ($voicemailBox['transcribe'] ?? false),
            'require_pin' => (bool) ($voicemailBox['require_pin'] ?? false),
            'is_setup' => array_key_exists('is_setup', $voicemailBox) ? (bool) $voicemailBox['is_setup'] : null,
            'switch_json' => $this->redactSensitiveData->handle($voicemailBox),
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function mapVoicemailMessage(array $message): array
    {
        $sourceTimestamp = is_int($message['timestamp'] ?? null) ? $message['timestamp'] : null;
        $unixTimestamp = $sourceTimestamp === null ? null : $sourceTimestamp - self::GREGORIAN_UNIX_OFFSET;
        $transcription = is_array($message['transcription'] ?? null) ? $message['transcription'] : [];

        return [
            'folder' => $this->stringValue($message['folder'] ?? null),
            'caller_id_name' => $this->stringValue($message['caller_id_name'] ?? null),
            'caller_id_number' => $this->stringValue($message['caller_id_number'] ?? null),
            'from_address' => $this->stringValue($message['from'] ?? null),
            'to_address' => $this->stringValue($message['to'] ?? null),
            'length' => is_int($message['length'] ?? null) && $message['length'] >= 0 ? $message['length'] : null,
            'source_timestamp' => $sourceTimestamp,
            'occurred_at' => $unixTimestamp !== null && $unixTimestamp >= 0
                ? CarbonImmutable::createFromTimestampUTC($unixTimestamp)
                : null,
            'transcription_result' => $this->stringValue($transcription['result'] ?? null),
            'transcription_text' => $this->stringValue($transcription['text'] ?? null),
            'switch_json' => $this->redactSensitiveData->handle($message),
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $messages
     * @return array<string, array<string, mixed>>
     */
    private function mapVoicemailMessages(iterable $messages): array
    {
        $records = [];

        foreach ($messages as $message) {
            $resourceId = $this->stringValue($message['media_id'] ?? null);

            if ($resourceId !== null) {
                $records[$resourceId] = $this->mapVoicemailMessage($message);
            }
        }

        return $records;
    }

    /**
     * @param  iterable<int, CallflowSnapshot>  $callflows
     * @param  array<string, array<string, mixed>>  $extensionRecords
     * @return array<string, array<string, mixed>>
     */
    private function mapCallflows(iterable $callflows, array $extensionRecords): array
    {
        $ownersByExtension = [];

        foreach ($extensionRecords as $resourceId => $extension) {
            if (is_string($extension['extension']) && $extension['extension'] !== '') {
                $ownersByExtension[$extension['extension']] = $resourceId;
            }
        }

        $records = [];

        foreach ($callflows as $callflow) {
            $ownerId = null;

            foreach ($callflow->numbers as $number) {
                if (isset($ownersByExtension[$number])) {
                    $ownerId = $ownersByExtension[$number];
                    break;
                }
            }

            $records[$callflow->id] = [
                'owner_switch_resource_id' => $ownerId,
                'name' => $callflow->name,
                'numbers' => $callflow->numbers,
                'patterns' => $callflow->patterns,
                'flags' => $callflow->flags,
                'modules' => $callflow->modules,
                'root_module' => $callflow->flow?->module,
                'node_count' => $callflow->nodeCount,
                'max_depth' => $callflow->maxDepth,
                'is_feature_code' => $callflow->featureCodeName !== null || $callflow->featureCodeNumber !== null,
                'feature_code_name' => $callflow->featureCodeName,
                'feature_code_number' => $callflow->featureCodeNumber,
                'flow_structure' => $callflow->flow?->toArray(),
                'switch_json' => $this->redactSensitiveData->handle($callflow->toArray()),
            ];
        }

        return $records;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
