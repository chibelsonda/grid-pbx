<?php

namespace App\Domains\CallRouting\Resources;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\CallRouting\Services\CallflowPublicTreeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchCallflow */
class CallflowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'route_type' => $this->routeType(),
            'numbers' => $this->numbers ?? [],
            'patterns' => $this->patterns ?? [],
            'flags' => $this->flags ?? [],
            'modules' => $this->modules ?? [],
            'root_module' => $this->root_module,
            'node_count' => $this->node_count,
            'max_depth' => $this->max_depth,
            'feature_code' => $this->is_feature_code ? [
                'name' => $this->feature_code_name,
                'number' => $this->feature_code_number,
            ] : null,
            'flow' => $this->flow_structure === null
                ? null
                : app(CallflowPublicTreeService::class)->transform($this->flow_structure),
            'linked_extension' => $this->extension === null ? null : [
                'id' => $this->extension->id,
                'display_name' => $this->extension->display_name,
                'extension' => $this->extension->extension,
            ],
            'phone_numbers' => $this->phoneNumbers->map(fn ($phoneNumber): array => [
                'id' => $phoneNumber->id,
                'number' => $phoneNumber->number,
                'state' => $phoneNumber->state,
            ])->values()->all(),
            'sync_status' => $this->sync_status->value,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }

    private function routeType(): string
    {
        return match (true) {
            $this->is_feature_code => 'feature_code',
            $this->phoneNumbers->isNotEmpty() => 'phone_number',
            $this->extension !== null => 'extension',
            ($this->patterns ?? []) !== [] => 'pattern',
            default => 'unassigned',
        };
    }
}
