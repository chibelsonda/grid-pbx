<?php

namespace App\Domains\PhoneNumbers\Resources;

use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchPhoneNumber */
class PhoneNumberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'state' => $this->state,
            'used_by' => $this->used_by,
            'carrier_name' => $this->carrier_name,
            'features' => $this->features ?? [],
            'cnam' => [
                'display_name' => $this->cnam_display_name,
                'inbound_lookup' => $this->cnam_inbound_lookup,
            ],
            'e911_status' => $this->e911_status,
            'assigned_callflow' => $this->assignedCallflow === null ? null : [
                'id' => $this->assignedCallflow->id,
                'name' => $this->assignedCallflow->name,
                'numbers' => $this->assignedCallflow->numbers,
            ],
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
