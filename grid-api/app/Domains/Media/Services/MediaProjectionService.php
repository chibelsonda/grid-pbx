<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use UnexpectedValueException;

class MediaProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactSensitiveData) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchMedia
    {
        $resourceId = $this->stringValue($snapshot['id'] ?? null);
        $name = $this->stringValue($snapshot['name'] ?? null);

        if ($resourceId === null || $name === null) {
            throw new UnexpectedValueException('Switch media response is missing required metadata.');
        }

        $media = SwitchMedia::withTrashed()->firstOrNew([
            'switch_account_id' => $account->getKey(),
            'switch_resource_id' => $resourceId,
        ]);
        $media->fill([
            'name' => $name,
            'description' => $this->stringValue($snapshot['description'] ?? null),
            'language' => $this->stringValue($snapshot['language'] ?? null),
            'media_source' => $this->stringValue($snapshot['media_source'] ?? null),
            'content_type' => $this->stringValue($snapshot['content_type'] ?? null),
            'content_length' => $this->nonNegativeInteger($snapshot['content_length'] ?? null),
            'prompt_id' => $this->stringValue($snapshot['prompt_id'] ?? null),
            'source_type' => $this->stringValue($snapshot['source_type'] ?? null),
            'source_resource_id' => $this->stringValue($snapshot['source_id'] ?? null),
            'streamable' => (bool) ($snapshot['streamable'] ?? true),
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $media->deleted_at = null;
        $media->save();

        return $media->refresh();
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
