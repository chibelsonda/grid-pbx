<?php

namespace App\Domains\SwitchSynchronization\Models;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'sync_run_id';

    protected $table = 'switch_sync_runs';

    protected $fillable = [
        'switch_account_id',
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

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id', 'user_id');
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
