<?php

namespace App\Domains\Payments\Resources;

use Illuminate\Http\Request;

class PaymentAttemptDetailResource extends PaymentAttemptResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'events' => PaymentAttemptEventResource::collection($this->resource->events)
                ->resolve($request),
        ];
    }
}
