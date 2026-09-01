<?php

namespace App\Domains\IdentityAccess\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    public function __construct(private readonly AuditService $audit) {}

    public function updateName(User $user, string $name, ?string $ipAddress = null): User
    {
        return DB::transaction(function () use ($user, $name, $ipAddress): User {
            $user->update(['name' => $name]);
            $this->audit->record(
                $user,
                null,
                'profile.name_updated',
                'succeeded',
                $user->id,
                [],
                $ipAddress,
                'user',
            );

            return $user->refresh();
        });
    }
}
