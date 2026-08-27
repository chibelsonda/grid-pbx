<?php

namespace App\Domains\Extensions\Infrastructure\Models;

use App\Domains\KazooSynchronization\Domain\ProjectionStatus;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use Database\Factories\KazooExtensionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KazooExtension extends Model
{
    /** @use HasFactory<KazooExtensionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'kazoo_account_id',
        'kazoo_resource_id',
        'username',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'extension',
        'timezone',
        'is_enabled',
        'source_revision',
        'source_updated_at',
        'last_synced_at',
        'sync_status',
        'projection_version',
    ];

    /** @return BelongsTo<KazooAccount, $this> */
    public function kazooAccount(): BelongsTo
    {
        return $this->belongsTo(KazooAccount::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
        ];
    }

    protected static function newFactory(): KazooExtensionFactory
    {
        return KazooExtensionFactory::new();
    }
}
