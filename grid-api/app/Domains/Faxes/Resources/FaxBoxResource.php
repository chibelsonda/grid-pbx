<?php

namespace App\Domains\Faxes\Resources;

use App\Domains\Faxes\Models\SwitchFaxBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchFaxBox */
class FaxBoxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : ['id' => $this->owner->id, 'label' => $this->owner->display_name, 'extension' => $this->owner->extension]),
            'caller_id' => $this->caller_id, 'caller_name' => $this->caller_name, 'fax_header' => $this->fax_header,
            'fax_identity' => $this->fax_identity, 'fax_timezone' => $this->fax_timezone, 'retries' => $this->retries,
            't38_enabled' => $this->t38_enabled, 'smtp_email_address' => $this->smtp_email_address,
            'custom_smtp_email_address' => $this->custom_smtp_email_address, 'smtp_permission_list' => $this->smtp_permission_list ?? [],
            'inbound_notification_emails' => $this->inbound_notification_emails ?? [], 'outbound_notification_emails' => $this->outbound_notification_emails ?? [],
            'fax_count' => $this->whenCounted('faxes'), 'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value,
        ];
    }
}
