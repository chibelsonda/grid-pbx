<?php

namespace App\Domains\Dashboard\Requests;

class CallQualityRequest extends CallActivityTrendRequest
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'range.in' => 'The selected call-quality range is invalid.',
        ];
    }
}
