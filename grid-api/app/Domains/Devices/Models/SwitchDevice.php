<?php

namespace App\Domains\Devices\Models;

use App\Domains\Devices\Enums\DeviceRegistrationStatus;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\LineKeys\Models\SwitchLineKey;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchDeviceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchDevice extends Model
{
    /** @use HasFactory<SwitchDeviceFactory> */
    use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'device_id';

    protected $fillable = [
        'switch_account_id',
        'switch_extension_id',
        'switch_resource_id',
        'owner_switch_resource_id',
        'name',
        'device_type',
        'make',
        'endpoint_family',
        'model',
        'mac_address',
        'is_enabled',
        'registration_status',
        'registration_checked_at',
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

    /** @return HasMany<SwitchLineKey, $this> */
    public function lineKeys(): HasMany
    {
        return $this->hasMany(SwitchLineKey::class, 'switch_device_id', 'device_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'registration_status' => DeviceRegistrationStatus::class,
            'registration_checked_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'is_managed' => 'boolean',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchDeviceFactory
    {
        return SwitchDeviceFactory::new();
    }
}
