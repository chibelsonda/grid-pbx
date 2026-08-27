<?php

namespace App\Domains\Organizations\Infrastructure\Models;

use App\Domains\Extensions\Infrastructure\Models\KazooExtension;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncCheckpoint;
use App\Domains\KazooSynchronization\Infrastructure\Models\SyncRun;
use Database\Factories\KazooAccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KazooAccount extends Model
{
    /** @use HasFactory<KazooAccountFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'kazoo_account_id',
        'name',
        'realm',
        'is_enabled',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<KazooExtension, $this> */
    public function extensions(): HasMany
    {
        return $this->hasMany(KazooExtension::class);
    }

    /** @return HasMany<SyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    /** @return HasMany<SyncCheckpoint, $this> */
    public function syncCheckpoints(): HasMany
    {
        return $this->hasMany(SyncCheckpoint::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    protected static function newFactory(): KazooAccountFactory
    {
        return KazooAccountFactory::new();
    }
}
