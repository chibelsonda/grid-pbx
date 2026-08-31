<?php

namespace App\Domains\Extensions\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ExtensionCoreAdvancedRules
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $rules = [
            'caller_id' => ['sometimes', 'array:internal,external,emergency'],
            'caller_id.internal' => ['required_with:caller_id', 'array:name,number'],
            'caller_id.internal.name' => ['nullable', 'string', 'max:35'],
            'caller_id.internal.number' => ['nullable', 'string', 'max:35'],
            'caller_id.external' => [
                'required_with:caller_id',
                'array:name,phone_number_id,preserve_number',
            ],
            'caller_id.external.name' => ['nullable', 'string', 'max:35'],
            'caller_id.external.phone_number_id' => ['nullable', 'uuid'],
            'caller_id.external.preserve_number' => ['required_with:caller_id', 'boolean'],
            'caller_id.emergency' => [
                'required_with:caller_id',
                'array:name,phone_number_id,preserve_number',
            ],
            'caller_id.emergency.name' => ['nullable', 'string', 'max:35'],
            'caller_id.emergency.phone_number_id' => ['nullable', 'uuid'],
            'caller_id.emergency.preserve_number' => ['required_with:caller_id', 'boolean'],
            'call_forward' => [
                'sometimes',
                'array:enabled,number,direct_calls_only,failover,ignore_early_media,keep_caller_id,require_keypress,substitute',
            ],
            'call_forward.enabled' => ['required_with:call_forward', 'boolean'],
            'call_forward.number' => [
                'nullable',
                'string',
                'max:35',
                'regex:/^[0-9+*#(),.\-\s]+$/',
                'required_if:call_forward.enabled,true',
            ],
            'call_forward.direct_calls_only' => ['required_with:call_forward', 'boolean'],
            'call_forward.failover' => ['required_with:call_forward', 'boolean'],
            'call_forward.ignore_early_media' => ['required_with:call_forward', 'boolean'],
            'call_forward.keep_caller_id' => ['required_with:call_forward', 'boolean'],
            'call_forward.require_keypress' => ['required_with:call_forward', 'boolean'],
            'call_forward.substitute' => ['required_with:call_forward', 'boolean'],
            'call_restriction' => ['sometimes', 'array', 'max:100'],
            'call_restriction.*' => ['array:action'],
            'call_restriction.*.action' => ['required', Rule::in(['inherit', 'deny'])],
            'call_recording' => ['sometimes', 'array:any,inbound,outbound'],
        ];

        foreach (['any', 'inbound', 'outbound'] as $direction) {
            $rules["call_recording.{$direction}"] = [
                'required_with:call_recording',
                'array:any,onnet,offnet',
            ];

            foreach (['any', 'onnet', 'offnet'] as $network) {
                $path = "call_recording.{$direction}.{$network}";
                $rules[$path] = [
                    'required_with:call_recording',
                    'array:enabled,format,record_min_sec,record_on_answer,record_on_bridge,record_sample_rate,time_limit',
                ];
                $rules["{$path}.enabled"] = ['required_with:call_recording', 'boolean'];
                $rules["{$path}.format"] = [
                    'required_with:call_recording',
                    Rule::in(['mp3', 'wav']),
                ];
                $rules["{$path}.record_min_sec"] = ['nullable', 'integer', 'min:0', 'max:3600'];
                $rules["{$path}.record_on_answer"] = ['required_with:call_recording', 'boolean'];
                $rules["{$path}.record_on_bridge"] = ['required_with:call_recording', 'boolean'];
                $rules["{$path}.record_sample_rate"] = [
                    'nullable',
                    'integer',
                    Rule::in([8000, 16000, 32000, 48000]),
                ];
                $rules["{$path}.time_limit"] = ['nullable', 'integer', 'min:5', 'max:10800'];
            }
        }

        return $rules;
    }

    /** @param array<string, mixed> $input */
    public static function validate(Validator $validator, array $input): void
    {
        $restrictions = $input['call_restriction'] ?? [];

        if (! is_array($restrictions)) {
            return;
        }

        foreach (array_keys($restrictions) as $classification) {
            if (! is_string($classification)
                || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $classification) !== 1) {
                $validator->errors()->add(
                    'call_restriction',
                    'A call restriction contains an invalid classification key.',
                );

                return;
            }
        }
    }
}
