<?php

namespace App\Domains\Organizations\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountHierarchyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'account' => $this->resource['account'],
            'parent' => $this->resource['parent'],
            'ancestors' => $this->resource['ancestors'],
            'children' => $this->resource['children'],
            'descendants' => $this->resource['descendants'],
            'coverage' => $this->resource['coverage'],
            'projection' => $this->resource['projection'],
        ];
    }
}
