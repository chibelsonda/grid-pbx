<?php

namespace App\Domains\Conferences\Models;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchConferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchConference extends Model
{
    /** @use HasFactory<SwitchConferenceFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'conference_id';

    protected $fillable = [
        'switch_account_id', 'owner_extension_id', 'switch_resource_id', 'owner_switch_resource_id',
        'name', 'member_pin_configured', 'moderator_pin_configured', 'member_join_muted',
        'member_join_deaf', 'member_play_entry_prompt', 'moderator_join_muted', 'moderator_join_deaf',
        'max_participants', 'language', 'profile_name', 'caller_controls', 'moderator_controls',
        'play_name', 'play_welcome', 'require_moderator', 'wait_for_moderator', 'active_members',
        'active_moderators', 'duration_seconds', 'is_locked', 'last_synced_at', 'sync_status',
        'projection_version', 'switch_json',
    ];

    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(SwitchExtension::class, 'owner_extension_id', 'extension_id');
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(SwitchConferenceNumber::class, 'switch_conference_id', 'conference_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'member_pin_configured' => 'boolean', 'moderator_pin_configured' => 'boolean',
            'member_join_muted' => 'boolean', 'member_join_deaf' => 'boolean',
            'member_play_entry_prompt' => 'boolean', 'moderator_join_muted' => 'boolean',
            'moderator_join_deaf' => 'boolean', 'max_participants' => 'integer',
            'play_name' => 'boolean', 'play_welcome' => 'boolean', 'require_moderator' => 'boolean',
            'wait_for_moderator' => 'boolean', 'active_members' => 'integer',
            'active_moderators' => 'integer', 'duration_seconds' => 'integer', 'is_locked' => 'boolean',
            'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer', 'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchConferenceFactory
    {
        return SwitchConferenceFactory::new();
    }
}
