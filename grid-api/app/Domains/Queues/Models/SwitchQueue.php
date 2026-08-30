<?php

namespace App\Domains\Queues\Models;

use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchQueueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchQueue extends Model
{
    /** @use HasFactory<SwitchQueueFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'queue_id';

    protected $fillable = [
        'switch_account_id', 'music_on_hold_media_id', 'switch_resource_id', 'name', 'strategy',
        'agent_ring_timeout', 'agent_wrapup_time', 'connection_timeout', 'max_queue_size',
        'ring_simultaneously', 'enter_when_empty', 'record_caller', 'caller_exit_key',
        'music_on_hold_reference', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json',
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

    /** @return HasMany<SwitchQueueAgent, $this> */
    public function agents(): HasMany
    {
        return $this->hasMany(SwitchQueueAgent::class, 'switch_queue_id', 'queue_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'agent_ring_timeout' => 'integer', 'agent_wrapup_time' => 'integer',
            'connection_timeout' => 'integer', 'max_queue_size' => 'integer',
            'ring_simultaneously' => 'integer', 'enter_when_empty' => 'boolean',
            'record_caller' => 'boolean', 'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchQueueFactory
    {
        return SwitchQueueFactory::new();
    }
}
