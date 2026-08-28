<?php

namespace App\Domains\TemporalRouting\Resources;

use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchTemporalRule */
class TemporalRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'cycle' => $this->cycle, 'interval' => $this->interval, 'start_date' => $this->start_date?->format('Y-m-d'), 'time_window_start' => $this->time_window_start, 'time_window_stop' => $this->time_window_stop, 'enabled' => $this->enabled, 'effective_status' => $this->getAttribute('effective_status'), 'days' => $this->days ?? [], 'weekdays' => $this->weekdays ?? [], 'month' => $this->month, 'ordinal' => $this->ordinal, 'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value];
    }
}
