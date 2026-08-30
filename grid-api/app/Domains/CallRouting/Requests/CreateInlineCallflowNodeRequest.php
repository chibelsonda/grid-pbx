<?php

namespace App\Domains\CallRouting\Requests;

use App\Domains\CallRouting\Rules\CallflowPublicBranchRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInlineCallflowNodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parent_path' => ['present', 'array', 'max:32'],
            'parent_path.*' => ['required', 'string', new CallflowPublicBranchRule],
            'branch' => ['required', 'string', new CallflowPublicBranchRule],
            'module' => ['required', 'string', Rule::in([
                'sleep', 'tts', 'collect_dtmf', 'record_call', 'record_caller',
                'send_dtmf', 'flush_dtmf', 'dead_air', 'language', 'response', 'hangup', 'set_variable',
                'branch_variable',
                'missed_call_alert',
                'set_cid', 'prepend_cid', 'set_alert_info', 'check_cid', 'cidlistmatch',
                'temporal_route', 'ring_group_toggle', 'hotdesk', 'do_not_disturb', 'call_forward',
            ])],
            'data' => ['required', 'array'],
        ];
    }
}
