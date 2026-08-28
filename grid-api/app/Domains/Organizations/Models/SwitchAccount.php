<?php

namespace App\Domains\Organizations\Models;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchCallflow;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Extensions\Models\SwitchVoicemailBox;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use Database\Factories\SwitchAccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SwitchAccount extends Model
{
    /** @use HasFactory<SwitchAccountFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'switch_account_id',
        'name',
        'realm',
        'is_enabled',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<SwitchExtension, $this> */
    public function extensions(): HasMany
    {
        return $this->hasMany(SwitchExtension::class);
    }

    /** @return HasMany<SwitchDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(SwitchDevice::class);
    }

    /** @return HasMany<SwitchVoicemailBox, $this> */
    public function voicemailBoxes(): HasMany
    {
        return $this->hasMany(SwitchVoicemailBox::class);
    }

    /** @return HasMany<SwitchCallflow, $this> */
    public function callflows(): HasMany
    {
        return $this->hasMany(SwitchCallflow::class);
    }

    /** @return HasMany<SyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    /** @return HasMany<SyncCheckpoint, $this> */
    public function syncCheckpoints(): HasMany
    {
        return $this->hasMany(SyncCheckpoint::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    protected static function newFactory(): SwitchAccountFactory
    {
        return SwitchAccountFactory::new();
    }
}
