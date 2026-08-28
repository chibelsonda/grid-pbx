<?php

namespace App\Domains\Extensions\Resources;

use App\Domains\Extensions\Models\SwitchExtension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchExtension */
class ExtensionDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'extension' => $this->extension,
            'timezone' => $this->timezone,
            'is_enabled' => $this->is_enabled,
            'is_managed' => $this->is_managed,
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'devices' => $this->devices->map(fn ($device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'device_type' => $device->device_type,
                'make' => $device->make,
                'model' => $device->model,
                'mac_address' => $device->mac_address,
                'is_enabled' => $device->is_enabled,
                'is_managed' => $device->is_managed,
                'sync_status' => $device->sync_status->value,
                'last_synced_at' => $device->last_synced_at?->toIso8601String(),
            ])->all(),
            'voicemail_boxes' => $this->voicemailBoxes->map(fn ($voicemailBox): array => [
                'id' => $voicemailBox->id,
                'name' => $voicemailBox->name,
                'mailbox' => $voicemailBox->mailbox,
                'is_setup' => $voicemailBox->is_setup,
                'timezone' => $voicemailBox->timezone,
                'notification_emails' => $voicemailBox->notification_emails ?? [],
                'transcribe' => $voicemailBox->transcribe,
                'require_pin' => $voicemailBox->require_pin,
                'message_count' => (int) ($voicemailBox->messages_count ?? 0),
                'is_managed' => $voicemailBox->is_managed,
                'sync_status' => $voicemailBox->sync_status->value,
                'last_synced_at' => $voicemailBox->last_synced_at?->toIso8601String(),
            ])->all(),
            'callflows' => $this->callflows->map(fn ($callflow): array => [
                'id' => $callflow->id,
                'name' => $callflow->name,
                'numbers' => $callflow->numbers,
                'modules' => $callflow->modules,
                'is_managed' => $callflow->is_managed,
                'sync_status' => $callflow->sync_status->value,
                'last_synced_at' => $callflow->last_synced_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
