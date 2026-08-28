<?php

namespace App\Domains\TemporalRouting\Models;

use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchTemporalRuleSetRule extends Model
{
    use HasPublicUuid, HasUlids;

    protected $primaryKey = 'temporal_rule_set_rule_id';

    protected $fillable = ['switch_temporal_rule_set_id', 'switch_temporal_rule_id', 'switch_rule_resource_id', 'position'];

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(SwitchTemporalRuleSet::class, 'switch_temporal_rule_set_id', 'temporal_rule_set_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SwitchTemporalRule::class, 'switch_temporal_rule_id', 'temporal_rule_id');
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
