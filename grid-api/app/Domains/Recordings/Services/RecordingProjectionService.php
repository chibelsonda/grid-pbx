<?php

namespace App\Domains\Recordings\Services;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Recordings\Models\SwitchRecording;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Carbon\CarbonImmutable;
use UnexpectedValueException;

class RecordingProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redact) {}
    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchRecording
    {
        $resourceId = $this->requiredString($snapshot['switch_resource_id'] ?? null, 'identifier'); $startedUnix = $snapshot['started_at_unix'] ?? null;
        if (! is_int($startedUnix) || $startedUnix < 0) throw new UnexpectedValueException('Switch recording is missing a valid start timestamp.');
        $ownerId = $this->string($snapshot['owner_switch_resource_id'] ?? null); $cdrId = $this->string($snapshot['cdr_id'] ?? null); $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
        if (isset($data['url'])) $data['url'] = '[REDACTED]';
        $recording = SwitchRecording::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'switch_resource_id' => $resourceId]);
        $recording->fill(['switch_extension_id' => $ownerId === null ? null : SwitchExtension::query()->where('switch_account_id', $account->getKey())->where('switch_resource_id', $ownerId)->value('extension_id'), 'switch_call_detail_record_id' => $cdrId === null ? null : SwitchCallDetailRecord::query()->where('switch_account_id', $account->getKey())->where('switch_resource_id', $cdrId)->value('call_detail_record_id'), 'owner_switch_resource_id' => $ownerId, 'call_id' => $this->string($snapshot['call_id'] ?? null), 'cdr_id' => $cdrId, 'interaction_id' => $this->string($snapshot['interaction_id'] ?? null), 'direction' => $this->string($snapshot['direction'] ?? null), 'caller_id_name' => $this->string($snapshot['caller_id_name'] ?? null), 'caller_id_number' => $this->string($snapshot['caller_id_number'] ?? null), 'callee_id_name' => $this->string($snapshot['callee_id_name'] ?? null), 'callee_id_number' => $this->string($snapshot['callee_id_number'] ?? null), 'from_uri' => $this->string($snapshot['from_uri'] ?? null), 'to_uri' => $this->string($snapshot['to_uri'] ?? null), 'request_uri' => $this->string($snapshot['request_uri'] ?? null), 'started_at' => CarbonImmutable::createFromTimestampUTC($startedUnix), 'duration_seconds' => $this->integer($snapshot['duration_seconds'] ?? null), 'duration_milliseconds' => $this->integer($snapshot['duration_milliseconds'] ?? null), 'name' => $this->string($snapshot['name'] ?? null), 'description' => $this->string($snapshot['description'] ?? null), 'content_type' => $this->string($snapshot['content_type'] ?? null), 'content_length' => is_int($snapshot['content_length'] ?? null) ? $snapshot['content_length'] : null, 'media_source' => $this->string($snapshot['media_source'] ?? null), 'media_type' => $this->string($snapshot['media_type'] ?? null), 'source_type' => $this->string($snapshot['source_type'] ?? null), 'origin' => $this->string($snapshot['origin'] ?? null), 'has_audio' => (bool) ($snapshot['has_audio'] ?? false), 'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => $recording->exists ? $recording->projection_version + 1 : 1, 'switch_json' => $this->redact->handle($data)]);
        $recording->deleted_at = null; $recording->save(); return $recording;
    }
    private function string(mixed $value): ?string { return is_string($value) && $value !== '' ? $value : null; }
    private function requiredString(mixed $value, string $name): string { return $this->string($value) ?? throw new UnexpectedValueException("Switch recording is missing its {$name}."); }
    private function integer(mixed $value): int { return is_int($value) && $value >= 0 ? $value : 0; }
}
