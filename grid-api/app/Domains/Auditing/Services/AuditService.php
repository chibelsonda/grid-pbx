<?php

namespace App\Domains\Auditing\Services;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Str;

class AuditService
{
    /** @param array<string, mixed> $metadata */
    public function record(
        User $actor,
        ?SwitchAccount $account,
        string $action,
        string $outcome,
        ?string $resourceId,
        array $metadata = [],
        ?string $ipAddress = null,
        string $resourceType = 'device',
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor->getKey(),
            'organization_id' => $account?->organization_id,
            'switch_account_id' => $account?->getKey(),
            'request_id' => (string) Str::uuid(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'outcome' => $outcome,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
