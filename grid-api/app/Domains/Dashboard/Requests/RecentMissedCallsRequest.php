<?php

namespace App\Domains\Dashboard\Requests;

class RecentMissedCallsRequest extends CallActivityTrendRequest
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'range.in' => 'The selected missed-call range is invalid.',
        ];
    }
}
