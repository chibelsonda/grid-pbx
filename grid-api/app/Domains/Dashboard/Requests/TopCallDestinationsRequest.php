<?php

namespace App\Domains\Dashboard\Requests;

class TopCallDestinationsRequest extends CallActivityTrendRequest
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'range.in' => 'The selected call-destination range is invalid.',
        ];
    }
}
