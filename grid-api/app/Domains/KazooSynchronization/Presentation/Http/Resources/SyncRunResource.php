<?php

namespace App\Domains\KazooSynchronization\Presentation\Http\Resources;

use App\Domains\KazooSynchronization\Infrastructure\Models\SyncRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SyncRun */
class SyncRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resource_type' => $this->resource_type,
            'status' => $this->status->value,
            'processed_count' => $this->processed_count,
            'upserted_count' => $this->upserted_count,
            'deleted_count' => $this->deleted_count,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
