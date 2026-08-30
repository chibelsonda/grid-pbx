<?php

namespace App\Domains\CallRouting\Models;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchCallflowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchCallflow extends Model
{
    /** @use HasFactory<SwitchCallflowFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'callflow_id';

    protected $fillable = [
        'switch_account_id',
        'switch_extension_id',
        'switch_resource_id',
        'owner_switch_resource_id',
        'name',
        'numbers',
        'patterns',
        'flags',
        'modules',
        'root_module',
        'node_count',
        'max_depth',
        'is_feature_code',
        'feature_code_name',
        'feature_code_number',
        'flow_structure',
        'last_synced_at',
        'sync_status',
        'projection_version',
        'is_managed',
        'managed_by_workflow',
        'switch_json',
    ];

    public function canBeRingGroupToggleTarget(): bool
    {
        return ! $this->is_feature_code
            && in_array('ring_group', $this->modules ?? [], true);
    }

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

    /** @return HasMany<SwitchPhoneNumber, $this> */
    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(SwitchPhoneNumber::class, 'assigned_callflow_id', 'callflow_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'numbers' => 'array',
            'patterns' => 'array',
            'flags' => 'array',
            'modules' => 'array',
            'node_count' => 'integer',
            'max_depth' => 'integer',
            'is_feature_code' => 'boolean',
            'flow_structure' => 'array',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'is_managed' => 'boolean',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchCallflowFactory
    {
        return SwitchCallflowFactory::new();
    }
}
