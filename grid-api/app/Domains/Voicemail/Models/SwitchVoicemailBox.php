<?php

namespace App\Domains\Voicemail\Models;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchVoicemailBoxFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchVoicemailBox extends Model
{
    /** @use HasFactory<SwitchVoicemailBoxFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'voicemail_box_id';

    protected $table = 'switch_voicemail_boxes';

    protected $fillable = [
        'switch_account_id',
        'switch_extension_id',
        'switch_resource_id',
        'owner_switch_resource_id',
        'name',
        'mailbox',
        'timezone',
        'notification_emails',
        'transcribe',
        'require_pin',
        'is_setup',
        'last_synced_at',
        'sync_status',
        'projection_version',
        'is_managed',
        'managed_by_workflow',
        'switch_json',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return BelongsTo<SwitchExtension, $this> */
    public function extension(): BelongsTo
    {
        return $this->belongsTo(SwitchExtension::class, 'switch_extension_id', 'extension_id');
    }

    /** @return HasMany<SwitchVoicemailMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SwitchVoicemailMessage::class, 'switch_voicemail_box_id', 'voicemail_box_id');
    }

    /** @return HasOne<SwitchVoicemailGreeting, $this> */
    public function unavailableGreeting(): HasOne
    {
        return $this->hasOne(SwitchVoicemailGreeting::class, 'switch_voicemail_box_id', 'voicemail_box_id')
            ->where('type', 'unavailable');
    }

    public function pinConfigured(): bool
    {
        $value = $this->switch_json['pin_configured'] ?? null;

        return is_bool($value) ? $value : $this->require_pin;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'notification_emails' => 'array',
            'transcribe' => 'boolean',
            'require_pin' => 'boolean',
            'is_setup' => 'boolean',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'is_managed' => 'boolean',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchVoicemailBoxFactory
    {
        return SwitchVoicemailBoxFactory::new();
    }
}
