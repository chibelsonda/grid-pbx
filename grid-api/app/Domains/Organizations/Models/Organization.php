<?php

namespace App\Domains\Organizations\Models;

use App\Domains\IdentityAccess\Models\User;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasPublicUuid;

    protected $primaryKey = 'organization_id';

    protected $fillable = ['name', 'slug'];

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'organization_user',
            'organization_id',
            'user_id',
            'organization_id',
            'user_id',
        )->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<SwitchAccount, $this> */
    public function switchAccounts(): HasMany
    {
        return $this->hasMany(SwitchAccount::class, 'organization_id', 'organization_id');
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
