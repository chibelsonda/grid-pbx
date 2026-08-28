<?php

namespace App\Domains\Groups\Models;

use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchGroupFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchGroup extends Model
{
    /** @use HasFactory<SwitchGroupFactory> */
    use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'group_id';

    protected $fillable = [
        'switch_account_id', 'music_on_hold_media_id', 'switch_resource_id', 'name',
        'last_synced_at', 'sync_status', 'projection_version', 'switch_json',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return BelongsTo<SwitchMedia, $this> */
    public function musicOnHoldMedia(): BelongsTo
    {
        return $this->belongsTo(SwitchMedia::class, 'music_on_hold_media_id', 'media_id');
    }

    /** @return HasMany<SwitchGroupMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(SwitchGroupMember::class, 'switch_group_id', 'group_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer', 'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchGroupFactory
    {
        return SwitchGroupFactory::new();
    }
}
