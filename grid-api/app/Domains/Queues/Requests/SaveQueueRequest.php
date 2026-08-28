<?php

namespace App\Domains\Queues\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'strategy' => ['required', Rule::in(['round_robin', 'most_idle'])],
            'agent_ring_timeout' => ['required', 'integer', 'min:1', 'max:300'],
            'agent_wrapup_time' => ['required', 'integer', 'min:0', 'max:3600'],
            'connection_timeout' => ['required', 'integer', 'min:0', 'max:86400'],
            'max_queue_size' => ['required', 'integer', 'min:0', 'max:10000'],
            'ring_simultaneously' => ['required', 'integer', 'min:1', 'max:100'],
            'enter_when_empty' => ['required', 'boolean'],
            'record_caller' => ['required', 'boolean'],
            'caller_exit_key' => ['required', Rule::in(['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#'])],
            'music_on_hold_media_id' => ['nullable', 'uuid'],
            'agent_ids' => ['present', 'array'],
            'agent_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
