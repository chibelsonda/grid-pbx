<?php

namespace App\Domains\Voicemail\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use UnexpectedValueException;

class VoicemailGreetingProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactSensitiveData) {}

    /** @param array<string, mixed> $snapshot */
    public function project(
        SwitchAccount $account,
        SwitchVoicemailBox $voicemailBox,
        array $snapshot,
    ): SwitchVoicemailGreeting {
        $resourceId = $this->stringValue($snapshot['id'] ?? null);

        if ($resourceId === null) {
            throw new UnexpectedValueException('Switch media response is missing its resource identifier.');
        }

        $greeting = SwitchVoicemailGreeting::withTrashed()->firstOrNew([
            'switch_voicemail_box_id' => $voicemailBox->getKey(),
            'type' => 'unavailable',
        ]);
        $greeting->fill([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $resourceId,
            'name' => $this->stringValue($snapshot['name'] ?? null),
            'description' => $this->stringValue($snapshot['description'] ?? null),
            'content_type' => $this->stringValue($snapshot['content_type'] ?? null),
            'content_length' => is_int($snapshot['content_length'] ?? null) ? $snapshot['content_length'] : null,
            'media_source' => $this->stringValue($snapshot['media_source'] ?? null),
            'streamable' => (bool) ($snapshot['streamable'] ?? true),
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $greeting->deleted_at = null;
        $greeting->save();

        return $greeting->refresh();
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
