<?php

namespace App\Domains\Queues\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'announce_media_id' => ['nullable', 'uuid'],
            'max_priority' => [Rule::prohibitedIf($this->route('queue') !== null), 'nullable', 'integer', 'min:0', 'max:255'],
            'announcements_enabled' => ['required', 'boolean'],
            'announcement_interval' => ['required_if:announcements_enabled,true', 'nullable', 'integer', 'min:15', 'max:86400'],
            'position_announcements_enabled' => ['required', 'boolean'],
            'wait_time_announcements_enabled' => ['required', 'boolean'],
            'announcement_in_the_queue_media_id' => ['nullable', 'uuid'],
            'announcement_increase_in_call_volume_media_id' => ['nullable', 'uuid'],
            'announcement_estimated_wait_time_media_id' => ['nullable', 'uuid'],
            'announcement_position_media_id' => ['nullable', 'uuid'],
            'cdr_url' => ['prohibited'],
            'recording_url' => ['prohibited'],
            'agent_ids' => ['present', 'array'],
            'agent_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $fields = [
                'announcement_in_the_queue_media_id',
                'announcement_increase_in_call_volume_media_id',
                'announcement_estimated_wait_time_media_id',
                'announcement_position_media_id',
            ];
            $selected = collect($fields)->filter(fn (string $field): bool => $this->filled($field))->count();

            if ($selected > 0 && $selected < count($fields)) {
                $validator->errors()->add('announcement_media', 'Select all four custom announcement prompts or leave all four on the Switch defaults.');
            }
        }];
    }
}
