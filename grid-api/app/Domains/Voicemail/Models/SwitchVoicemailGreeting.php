<?php

namespace App\Domains\Voicemail\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchVoicemailGreetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchVoicemailGreeting extends Model
{
    /** @use HasFactory<SwitchVoicemailGreetingFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'voicemail_greeting_id';

    protected $fillable = [
        'switch_account_id',
        'switch_voicemail_box_id',
        'switch_resource_id',
        'type',
        'name',
        'description',
        'content_type',
        'content_length',
        'media_source',
        'streamable',
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

    /** @return BelongsTo<SwitchVoicemailBox, $this> */
    public function voicemailBox(): BelongsTo
    {
        return $this->belongsTo(SwitchVoicemailBox::class, 'switch_voicemail_box_id', 'voicemail_box_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'content_length' => 'integer',
            'streamable' => 'boolean',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchVoicemailGreetingFactory
    {
        return SwitchVoicemailGreetingFactory::new();
    }
}
