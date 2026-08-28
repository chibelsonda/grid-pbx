<?php

namespace App\Domains\Menus\Services;

use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use UnexpectedValueException;

class MenuProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactSensitiveData) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchMenu
    {
        $resourceId = $this->stringValue($snapshot['id'] ?? null);
        $name = $this->stringValue($snapshot['name'] ?? null);

        if ($resourceId === null || $name === null) {
            throw new UnexpectedValueException('Switch menu response is missing required metadata.');
        }

        $media = is_array($snapshot['media'] ?? null) ? $snapshot['media'] : [];
        $greeting = $this->stringValue($media['greeting'] ?? null);
        [$invalidEnabled, $invalidReference] = $this->mediaSetting($media['invalid_media'] ?? true);
        [$transferEnabled, $transferReference] = $this->mediaSetting($media['transfer_media'] ?? true);
        [$exitEnabled, $exitReference] = $this->mediaSetting($media['exit_media'] ?? true);
        $menu = SwitchMenu::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'switch_resource_id' => $resourceId]);
        $mediaId = fn (?string $reference) => $reference === null ? null : $account->media()->where('switch_resource_id', $reference)->value('media_id');
        $menu->fill([
            'name' => $name, 'timeout' => max(1, min(60000, (int) ($snapshot['timeout'] ?? 10000))),
            'interdigit_timeout' => max(1, min(10000, (int) ($snapshot['interdigit_timeout'] ?? 2000))),
            'max_extension_length' => max(1, min(6, (int) ($snapshot['max_extension_length'] ?? 4))),
            'retries' => max(1, min(10, (int) ($snapshot['retries'] ?? 3))),
            'hunt' => (bool) ($snapshot['hunt'] ?? true), 'allow_record_from_offnet' => (bool) ($snapshot['allow_record_from_offnet'] ?? false),
            'suppress_media' => (bool) ($snapshot['suppress_media'] ?? false), 'record_pin_configured' => $this->stringValue($snapshot['record_pin'] ?? null) !== null,
            'hunt_allow' => $this->stringValue($snapshot['hunt_allow'] ?? null), 'hunt_deny' => $this->stringValue($snapshot['hunt_deny'] ?? null),
            'greeting_media_reference' => $greeting, 'greeting_media_id' => $mediaId($greeting),
            'invalid_media_enabled' => $invalidEnabled, 'invalid_media_reference' => $invalidReference, 'invalid_media_id' => $mediaId($invalidReference),
            'transfer_media_enabled' => $transferEnabled, 'transfer_media_reference' => $transferReference, 'transfer_media_id' => $mediaId($transferReference),
            'exit_media_enabled' => $exitEnabled, 'exit_media_reference' => $exitReference, 'exit_media_id' => $mediaId($exitReference),
            'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $menu->exists ? $menu->projection_version + 1 : 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ]);
        $menu->deleted_at = null;
        $menu->save();

        return $menu->load(['greetingMedia', 'invalidMedia', 'transferMedia', 'exitMedia']);
    }

    /** @return array{bool, ?string} */
    private function mediaSetting(mixed $value): array
    {
        return is_string($value) && $value !== '' ? [true, $value] : [(bool) $value, null];
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
