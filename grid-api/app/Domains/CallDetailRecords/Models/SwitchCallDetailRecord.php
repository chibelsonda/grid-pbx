<?php

namespace App\Domains\CallDetailRecords\Models;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Recordings\Models\SwitchRecording;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchCallDetailRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SwitchCallDetailRecord extends Model
{
    /** @use HasFactory<SwitchCallDetailRecordFactory> */
    use HasFactory, HasPublicUuid;

    protected $primaryKey = 'call_detail_record_id';

    protected $fillable = [
        'switch_account_id',
        'switch_extension_id',
        'switch_resource_id',
        'call_id',
        'interaction_id',
        'direction',
        'caller_id_name',
        'caller_id_number',
        'callee_id_name',
        'callee_id_number',
        'from_uri',
        'to_uri',
        'request_uri',
        'started_at',
        'duration_seconds',
        'billing_seconds',
        'hangup_cause',
        'disposition',
        'recording_available',
        'last_synced_at',
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

    /** @return HasMany<SwitchRecording, $this> */
    public function recordings(): HasMany
    {
        return $this->hasMany(
            SwitchRecording::class,
            'switch_call_detail_record_id',
            'call_detail_record_id',
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'duration_seconds' => 'integer',
            'billing_seconds' => 'integer',
            'recording_available' => 'boolean',
            'last_synced_at' => 'datetime',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchCallDetailRecordFactory
    {
        return SwitchCallDetailRecordFactory::new();
    }
}
