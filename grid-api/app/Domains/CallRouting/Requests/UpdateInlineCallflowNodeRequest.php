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
                'send_dtmf', 'flush_dtmf', 'dead_air', 'language', 'response', 'hangup', 'set_variable', 'set_variables', 'manual_presence', 'group_pickup', 'page_group', 'ring_group', 'receive_fax', 'conference', 'voicemail',
                'branch_variable',
                'branch_bnumber',
                'missed_call_alert',
                'set_cid', 'prepend_cid', 'set_alert_info', 'check_cid', 'cidlistmatch',
                'temporal_route', 'ring_group_toggle', 'hotdesk', 'do_not_disturb', 'call_forward',
            ])],
            'data' => ['required', 'array'],
        ];
    }
}
