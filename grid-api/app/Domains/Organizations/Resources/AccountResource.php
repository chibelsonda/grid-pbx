<?php

namespace App\Domains\Organizations\Resources;

use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchAccount */
class AccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = is_string($this->organization_role)
            ? OrganizationRole::tryFrom($this->organization_role)
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'realm' => $this->realm,
            'organization' => [
                'id' => $this->whenLoaded('organization', fn () => $this->organization->id),
                'name' => $this->whenLoaded('organization', fn () => $this->organization->name),
            ],
            'organization_role' => $role?->value,
            'permissions' => [
                'can_manage_extensions' => $role?->canManageDevices() ?? false,
                'can_manage_devices' => $role?->canManageDevices() ?? false,
                'can_manage_voicemail' => $role?->canManageVoicemail() ?? false,
                'can_manage_call_routing' => $role?->canManageCallRouting() ?? false,
                'can_sync_call_detail_records' => $role?->canSyncCallDetailRecords() ?? false,
            ],
        ];
    }
}
