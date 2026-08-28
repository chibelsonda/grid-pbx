<?php

namespace App\Domains\IdentityAccess\Models;

use App\Domains\Organizations\Models\Organization;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, Notifiable;

    protected $primaryKey = 'user_id';

    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class,
            'organization_user',
            'user_id',
            'organization_id',
            'user_id',
            'organization_id',
        )->withPivot('role')->withTimestamps();
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
