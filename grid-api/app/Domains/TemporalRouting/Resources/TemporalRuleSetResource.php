<?php

namespace App\Domains\TemporalRouting\Resources;

use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchTemporalRuleSet */
class TemporalRuleSetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'rule_count' => $this->whenCounted('rules'), 'rules' => $this->whenLoaded('rules', fn () => $this->rules->map(fn ($membership) => ['id' => $membership->id, 'rule' => $membership->rule === null ? null : ['id' => $membership->rule->id, 'name' => $membership->rule->name, 'cycle' => $membership->rule->cycle], 'position' => $membership->position, 'resolved' => $membership->rule !== null])->values()->all()), 'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value];
    }
}
