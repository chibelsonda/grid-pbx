<?php

namespace App\Domains\Extensions\Models;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchExtensionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchExtension extends Model
{
    /** @use HasFactory<SwitchExtensionFactory> */
    use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'extension_id';

    protected $fillable = [
        'switch_account_id',
        'switch_resource_id',
        'username',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'extension',
        'timezone',
        'is_enabled',
        'source_revision',
        'source_updated_at',
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

    /** @return HasMany<SwitchDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(SwitchDevice::class, 'switch_extension_id', 'extension_id');
    }

    /** @return HasMany<SwitchVoicemailBox, $this> */
    public function voicemailBoxes(): HasMany
    {
        return $this->hasMany(SwitchVoicemailBox::class, 'switch_extension_id', 'extension_id');
    }

    /** @return HasMany<SwitchCallflow, $this> */
    public function callflows(): HasMany
    {
        return $this->hasMany(SwitchCallflow::class, 'switch_extension_id', 'extension_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'is_managed' => 'boolean',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchExtensionFactory
    {
        return SwitchExtensionFactory::new();
    }
}
