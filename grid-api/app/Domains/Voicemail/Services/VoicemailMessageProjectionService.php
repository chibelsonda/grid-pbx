<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use Carbon\CarbonImmutable;
use UnexpectedValueException;

class VoicemailMessageProjectionService
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;

    public function __construct(private readonly RedactSensitiveSwitchData $redactSensitiveData) {}

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public function attributes(array $message): array
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

    /** @param array<string, mixed> $snapshot */
    public function refresh(SwitchVoicemailMessage $message, array $snapshot): SwitchVoicemailMessage
    {
        $resourceId = $this->stringValue($snapshot['media_id'] ?? null);

        if ($resourceId === null) {
            throw new UnexpectedValueException('Switch voicemail message response is missing its resource identifier.');
        }

        $message->fill($this->attributes($snapshot) + [
            'switch_resource_id' => $resourceId,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
        ]);
        $message->deleted_at = null;
        $message->save();

        return $message->refresh();
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
