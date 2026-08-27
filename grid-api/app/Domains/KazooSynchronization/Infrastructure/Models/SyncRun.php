<?php

namespace App\Domains\KazooSynchronization\Infrastructure\Models;

use App\Domains\IdentityAccess\Infrastructure\Models\User;
use App\Domains\KazooSynchronization\Domain\SyncRunStatus;
use App\Domains\Organizations\Infrastructure\Models\KazooAccount;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    use HasUlids;

    protected $table = 'kazoo_sync_runs';

    protected $fillable = [
        'kazoo_account_id',
        'requested_by_user_id',
        'resource_type',
        'status',
        'processed_count',
        'upserted_count',
        'deleted_count',
        'error_code',
        'error_message',
        'started_at',
        'finished_at',
    ];

    /** @return BelongsTo<KazooAccount, $this> */
    public function kazooAccount(): BelongsTo
    {
        return $this->belongsTo(KazooAccount::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SyncRunStatus::class,
            'processed_count' => 'integer',
            'upserted_count' => 'integer',
            'deleted_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
