<?php

namespace App\Domains\KazooSynchronization\Infrastructure\Models;

use App\Domains\KazooSynchronization\Domain\ProjectionStatus;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncCheckpoint extends Model
{
    use HasUlids;

    protected $table = 'kazoo_sync_checkpoints';

    protected $fillable = [
        'kazoo_account_id',
        'last_sync_run_id',
        'resource_type',
        'cursor',
        'status',
        'last_successful_at',
        'error_message',
    ];

    /** @return BelongsTo<KazooAccount, $this> */
    public function kazooAccount(): BelongsTo
    {
        return $this->belongsTo(KazooAccount::class);
    }

    /** @return BelongsTo<SyncRun, $this> */
    public function lastSyncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class, 'last_sync_run_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProjectionStatus::class,
            'last_successful_at' => 'datetime',
        ];
    }
}
