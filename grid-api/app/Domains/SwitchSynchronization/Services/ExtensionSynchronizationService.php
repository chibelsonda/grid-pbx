<?php

namespace App\Domains\SwitchSynchronization\Services;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchCallflow;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Extensions\Models\SwitchVoicemailBox;
use App\Domains\SwitchSynchronization\Contracts\SwitchExtensionGateway;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ExtensionSynchronizationService
{
    public function __construct(
        private readonly SwitchExtensionGateway $gateway,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
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
        $voicemailRecords = $this->mapResources($this->gateway->voicemailBoxes($account), $this->mapVoicemailBox(...));
        $callflowRecords = $this->mapCallflows($this->gateway->callflows($account), $extensionRecords);

        DB::transaction(function () use ($run, $account, $extensionRecords, $deviceRecords, $voicemailRecords, $callflowRecords): void {
            $syncedAt = now();
            $deletedCount = 0;
            $deletedCount += $this->synchronizeProjection(SwitchExtension::class, $account->getKey(), $extensionRecords, $syncedAt);
            $extensionIdsByResource = SwitchExtension::query()
                ->where('switch_account_id', $account->getKey())
                ->pluck('id', 'switch_resource_id')
                ->all();
            $deletedCount += $this->synchronizeProjection(SwitchDevice::class, $account->getKey(), $this->associateExtensions($deviceRecords, $extensionIdsByResource), $syncedAt);
            $deletedCount += $this->synchronizeProjection(SwitchVoicemailBox::class, $account->getKey(), $this->associateExtensions($voicemailRecords, $extensionIdsByResource), $syncedAt);
            $deletedCount += $this->synchronizeProjection(SwitchCallflow::class, $account->getKey(), $this->associateExtensions($callflowRecords, $extensionIdsByResource), $syncedAt);
            $processedCount = count($extensionRecords) + count($deviceRecords) + count($voicemailRecords) + count($callflowRecords);

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
            'source_payload' => $this->redactSensitiveData->handle($user),
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
            'model' => $this->stringValue($device['model'] ?? Arr::get($device, 'provision.endpoint_model')),
            'mac_address' => $this->stringValue($device['mac_address'] ?? Arr::get($device, 'provision.mac_address')),
            'is_enabled' => (bool) ($device['enabled'] ?? true),
            'source_payload' => $this->redactSensitiveData->handle($device),
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
            'is_setup' => array_key_exists('is_setup', $voicemailBox) ? (bool) $voicemailBox['is_setup'] : null,
            'source_payload' => $this->redactSensitiveData->handle($voicemailBox),
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $callflows
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

        return $this->mapResources($callflows, function (array $callflow) use ($ownersByExtension): array {
            $numbers = array_values(array_filter(
                is_array($callflow['numbers'] ?? null) ? $callflow['numbers'] : [],
                fn (mixed $number): bool => is_string($number) && $number !== '',
            ));
            $modules = array_values(array_filter(
                is_array($callflow['modules'] ?? null) ? $callflow['modules'] : [],
                fn (mixed $module): bool => is_string($module) && $module !== '',
            ));
            $ownerId = null;

            foreach ($numbers as $number) {
                if (isset($ownersByExtension[$number])) {
                    $ownerId = $ownersByExtension[$number];
                    break;
                }
            }

            return [
                'owner_switch_resource_id' => $ownerId,
                'name' => $this->stringValue($callflow['name'] ?? null),
                'numbers' => $numbers,
                'modules' => $modules,
                'source_payload' => $this->redactSensitiveData->handle($callflow),
            ];
        });
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
