<?php

namespace App\Domains\CallerIdLists\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchCallerIdList extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'caller_id_list_id';

    protected $fillable = [
        'switch_account_id',
        'switch_resource_id',
        'name',
        'description',
        'organization',
        'last_synced_at',
        'sync_status',
        'projection_version',
        'switch_json',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchCallerIdListEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(SwitchCallerIdListEntry::class, 'switch_caller_id_list_id', 'caller_id_list_id');
    }

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'switch_json' => 'array',
        ];
    }
}
