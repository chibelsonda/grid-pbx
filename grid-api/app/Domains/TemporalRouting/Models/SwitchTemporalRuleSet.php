<?php

namespace App\Domains\TemporalRouting\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchTemporalRuleSetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchTemporalRuleSet extends Model
{
    /** @use HasFactory<SwitchTemporalRuleSetFactory> */
    use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'temporal_rule_set_id';

    protected $fillable = ['switch_account_id', 'switch_resource_id', 'name', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json'];

    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(SwitchTemporalRuleSetRule::class, 'switch_temporal_rule_set_id', 'temporal_rule_set_id')->orderBy('position');
    }

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer', 'switch_json' => 'array'];
    }

    protected static function newFactory(): SwitchTemporalRuleSetFactory
    {
        return SwitchTemporalRuleSetFactory::new();
    }
}
