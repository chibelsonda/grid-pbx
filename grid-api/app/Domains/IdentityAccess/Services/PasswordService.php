<?php

namespace App\Domains\IdentityAccess\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordService
{
    public function __construct(private readonly AuditService $audit) {}

    public function update(User $user, string $password, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($user, $password, $ipAddress): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $this->audit->record(
                $user,
                null,
                'profile.password_updated',
                'succeeded',
                $user->id,
                [],
                $ipAddress,
                'user',
            );
        });
    }
}
