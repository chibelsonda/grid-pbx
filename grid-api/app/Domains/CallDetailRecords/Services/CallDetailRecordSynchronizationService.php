<?php

namespace App\Domains\CallDetailRecords\Services;

use App\Domains\CallDetailRecords\Contracts\SwitchCallDetailRecordGateway;
use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use UnexpectedValueException;

class CallDetailRecordSynchronizationService
{
    /** @var list<string> */
    private const SAFE_SNAPSHOT_FIELDS = [
        'id',
        'call_id',
        'interaction_id',
        'direction',
        'caller_id_name',
        'caller_id_number',
        'callee_id_name',
        'callee_id_number',
        'duration_seconds',
        'billing_seconds',
        'timestamp',
        'unix_timestamp',
        'hangup_cause',
        'disposition',
        'owner_id',
        'to',
        'from',
        'request',
        'dialed_number',
        'calling_from',
        'datetime',
        'iso_8601',
        'call_type',
        'media_server',
        'call_priority',
    ];

    public function __construct(
        private readonly SwitchCallDetailRecordGateway $gateway,
        private readonly RedactSensitiveSwitchData $redactSensitiveData,
    ) {}

    public function handle(SyncRun $run): void
    {
        $windowDays = (int) config('switch.cdr_import_window_days');

        if ($windowDays < 1 || $windowDays > 31) {
            throw new UnexpectedValueException('Switch CDR import window must be between 1 and 31 days.');
        }

        $run->update([
            'status' => SyncRunStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);

        $account = $run->switchAccount()->firstOrFail();
        $to = CarbonImmutable::now('UTC');
        $from = $to->subDays($windowDays);
        $syncedAt = now();
        $processedCount = 0;
        $extensionIdsByResource = SwitchExtension::query()
            ->where('switch_account_id', $account->getKey())
            ->pluck('extension_id', 'switch_resource_id')
            ->all();

        foreach ($this->gateway->all($account, $from, $to) as $snapshot) {
            $resourceId = $this->requiredString($snapshot['switch_resource_id'] ?? null, 'resource identifier');
            $callId = $this->requiredString($snapshot['call_id'] ?? null, 'call identifier');
            $startedAtUnix = $this->nonNegativeInteger($snapshot['started_at_unix'] ?? null);

            if ($startedAtUnix === null) {
                throw new UnexpectedValueException('Switch CDR is missing a valid start timestamp.');
            }

            $ownerResourceId = $this->stringValue($snapshot['owner_switch_resource_id'] ?? null);
            $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
            $record = SwitchCallDetailRecord::query()->firstOrNew([
                'switch_account_id' => $account->getKey(),
                'switch_resource_id' => $resourceId,
            ]);
            $record->fill([
                'switch_extension_id' => $ownerResourceId === null
                    ? null
                    : ($extensionIdsByResource[$ownerResourceId] ?? null),
                'call_id' => $callId,
                'interaction_id' => $this->stringValue($snapshot['interaction_id'] ?? null),
                'direction' => $this->stringValue($snapshot['direction'] ?? null),
                'caller_id_name' => $this->stringValue($snapshot['caller_id_name'] ?? null),
                'caller_id_number' => $this->stringValue($snapshot['caller_id_number'] ?? null),
                'callee_id_name' => $this->stringValue($snapshot['callee_id_name'] ?? null),
                'callee_id_number' => $this->stringValue($snapshot['callee_id_number'] ?? null),
                'from_uri' => $this->stringValue($snapshot['from_uri'] ?? null),
                'to_uri' => $this->stringValue($snapshot['to_uri'] ?? null),
                'request_uri' => $this->stringValue($snapshot['request_uri'] ?? null),
                'started_at' => CarbonImmutable::createFromTimestampUTC($startedAtUnix),
                'duration_seconds' => $this->nonNegativeInteger($snapshot['duration_seconds'] ?? null) ?? 0,
                'billing_seconds' => $this->nonNegativeInteger($snapshot['billing_seconds'] ?? null) ?? 0,
                'hangup_cause' => $this->stringValue($snapshot['hangup_cause'] ?? null),
                'disposition' => $this->stringValue($snapshot['disposition'] ?? null),
                'recording_available' => (bool) ($snapshot['recording_available'] ?? false),
                'last_synced_at' => $syncedAt,
                'switch_json' => $this->redactSensitiveData->handle(Arr::only(
                    $data,
                    self::SAFE_SNAPSHOT_FIELDS,
                )),
            ]);
            $record->save();
            $processedCount++;
        }

        $run->update([
            'status' => SyncRunStatus::Succeeded,
            'processed_count' => $processedCount,
            'upserted_count' => $processedCount,
            'deleted_count' => 0,
            'finished_at' => now(),
        ]);
        SyncCheckpoint::query()->updateOrCreate([
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'call_detail_records',
        ], [
            'last_sync_run_id' => $run->getKey(),
            'cursor' => null,
            'status' => ProjectionStatus::Healthy,
            'last_successful_at' => now(),
            'error_message' => null,
        ]);
    }

    private function requiredString(mixed $value, string $name): string
    {
        $value = $this->stringValue($value);

        if ($value === null) {
            throw new UnexpectedValueException("Switch CDR is missing its {$name}.");
        }

        return $value;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }
}
