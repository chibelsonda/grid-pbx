<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Rules\CallflowPublicBranchRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInlineCallflowNodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'node_path' => ['required', 'array', 'min:1', 'max:32'],
            'node_path.*' => ['required', 'string', new CallflowPublicBranchRule],
            'module' => ['required', 'string', Rule::in([
                'sleep', 'tts', 'collect_dtmf', 'record_call', 'record_caller',
                'send_dtmf', 'flush_dtmf', 'dead_air', 'language', 'missed_call_alert',
            ])],
            'data' => ['required', 'array'],
        ];
    }
}
