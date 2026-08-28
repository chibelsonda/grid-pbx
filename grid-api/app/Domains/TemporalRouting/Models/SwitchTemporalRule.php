<?php

namespace App\Domains\TemporalRouting\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchTemporalRuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchTemporalRule extends Model
{
    /** @use HasFactory<SwitchTemporalRuleFactory> */
    use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'temporal_rule_id';

    protected $fillable = ['switch_account_id', 'switch_resource_id', 'name', 'cycle', 'interval', 'start_date', 'switch_start_date', 'time_window_start', 'time_window_stop', 'enabled', 'days', 'weekdays', 'month', 'ordinal', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json'];

    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    public function ruleSetMemberships(): HasMany
    {
        return $this->hasMany(SwitchTemporalRuleSetRule::class, 'switch_temporal_rule_id', 'temporal_rule_id');
    }

    protected function casts(): array
    {
        return ['interval' => 'integer', 'start_date' => 'date:Y-m-d', 'switch_start_date' => 'integer', 'time_window_start' => 'integer', 'time_window_stop' => 'integer', 'enabled' => 'boolean', 'days' => 'array', 'weekdays' => 'array', 'month' => 'integer', 'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer', 'switch_json' => 'array'];
    }

    protected static function newFactory(): SwitchTemporalRuleFactory
    {
        return SwitchTemporalRuleFactory::new();
    }
}
