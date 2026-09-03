<?php

namespace App\Domains\CallRouting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $usesInlineRoot = fn (): bool => is_array($this->input('root_action'));
        $hasEndpointSettings = in_array($this->input('destination_type'), ['extension', 'device'], true);
        $strictBoolean = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_bool($value)) {
                $fail("The {$attribute} field must be true or false.");
            }
        };

        return [
            'name' => ['required', 'string', 'max:128'],
            'destination_type' => [Rule::requiredIf(fn (): bool => ! $usesInlineRoot()), Rule::prohibitedIf($usesInlineRoot), 'nullable', Rule::in(['extension', 'device', 'voicemail', 'callflow', 'media', 'directory', 'group', 'queue', 'menu', 'conference', 'fax_box', 'temporal_rule_set', 'temporal_rules'])],
            'destination_id' => [Rule::requiredIf(fn (): bool => ! $usesInlineRoot() && $this->input('destination_type') !== 'temporal_rules'), Rule::prohibitedIf($usesInlineRoot), 'nullable', 'uuid'],
            'destination_data' => ['sometimes', Rule::prohibitedIf(! $hasEndpointSettings), 'array:timeout,can_call_self'],
            'destination_data.timeout' => ['required_with:destination_data', 'integer', 'min:1', 'max:600'],
            'destination_data.can_call_self' => ['required_with:destination_data', $strictBoolean],
            'root_action' => ['nullable', 'array:module,data'],
            'root_action.module' => ['required_with:root_action', 'string', Rule::in(['ring_group', 'call_forward', 'dynamic_cid', 'pivot'])],
            'root_action.data' => ['required_with:root_action', 'array'],
            'temporal_rule_ids' => ['exclude_unless:destination_type,temporal_rules', 'required', 'array', 'min:1', 'max:50'],
            'temporal_rule_ids.*' => ['required', 'uuid', 'distinct'],
            'temporal_rule_routes' => ['exclude_unless:destination_type,temporal_rules', 'required', 'array', 'min:1', 'max:50'],
            'temporal_rule_routes.*.rule_id' => ['required', 'uuid', 'distinct'],
            'temporal_rule_routes.*.destination_type' => ['required', Rule::in(['extension', 'device', 'voicemail', 'callflow', 'media', 'directory', 'group', 'queue', 'menu', 'conference', 'fax_box', 'temporal_rule_set'])],
            'temporal_rule_routes.*.destination_id' => ['required', 'uuid'],
            'manage_fallback' => ['sometimes', 'boolean'],
            'fallback_destination_type' => ['nullable', 'required_with:fallback_destination_id', Rule::in(['extension', 'device', 'voicemail', 'callflow', 'media', 'directory', 'group', 'queue', 'menu', 'conference', 'fax_box', 'temporal_rule_set'])],
            'fallback_destination_id' => ['nullable', 'required_with:fallback_destination_type', 'uuid'],
            'manage_menu_branches' => ['sometimes', 'boolean'],
            'menu_branches' => ['required_if:manage_menu_branches,true', 'array', 'max:12'],
            'menu_branches.*.key' => ['required', 'string', 'distinct', Rule::in(['timeout', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*'])],
            'menu_branches.*.destination_type' => ['required', Rule::in(['extension', 'device', 'voicemail', 'callflow', 'media', 'directory', 'group', 'queue', 'menu', 'conference', 'fax_box', 'temporal_rule_set'])],
            'menu_branches.*.destination_id' => ['required', 'uuid'],
            'manage_temporal_match' => ['sometimes', 'boolean'],
            'temporal_match_destination_type' => ['nullable', 'required_with:temporal_match_destination_id', Rule::in(['extension', 'device', 'voicemail', 'callflow', 'media', 'directory', 'group', 'queue', 'menu', 'conference', 'fax_box', 'temporal_rule_set'])],
            'temporal_match_destination_id' => ['nullable', 'required_with:temporal_match_destination_type', 'uuid'],
            'phone_number_ids' => ['present', 'array', 'required_without:extension_numbers', 'max:25'],
            'phone_number_ids.*' => ['uuid', 'distinct'],
            'extension_numbers' => ['sometimes', 'array', 'required_without:phone_number_ids', 'max:25'],
            'extension_numbers.*' => ['required', 'string', 'regex:/^[0-9]{2,15}$/', 'distinct'],
        ];
    }
}
