<?php

namespace App\Domains\SwitchSynchronization\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncCheckpoint extends Model
{
    use HasUlids;

    protected $table = 'switch_sync_checkpoints';

    protected $fillable = [
        'switch_account_id',
        'last_sync_run_id',
        'resource_type',
        'cursor',
        'status',
        'last_successful_at',
        'error_message',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class);
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
