<?php

namespace App\Domains\CallRouting\Services;

use App\Shared\Validation\Rules\SafeSwitchRegex;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CallflowInlineNodeDataValidator
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function validate(string $module, array $data): array
    {
        $strictBoolean = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_bool($value)) {
                $fail("The {$attribute} field must be true or false.");
            }
        };

        $rules = match ($module) {
            'sleep' => [
                'data' => ['required', 'array:duration,unit,skip_module'],
                'data.duration' => ['required', 'integer', 'min:0', 'max:86400000'],
                'data.unit' => ['required', 'string', Rule::in(['ms', 's', 'm', 'h'])],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'tts' => [
                'data' => ['required', 'array:text,voice,language,engine,endless_playback,terminators,skip_module'],
                'data.text' => ['required', 'string', 'min:1', 'max:1000'],
                'data.voice' => ['present', 'nullable', 'string', 'max:64'],
                'data.language' => ['present', 'nullable', 'string', 'max:35'],
                'data.engine' => ['present', 'nullable', 'string', Rule::in(['flite', 'google', 'ispeech', 'voicefabric'])],
                'data.endless_playback' => ['required', 'boolean'],
                'data.terminators' => ['required', 'array', 'max:12'],
                'data.terminators.*' => ['required', 'string', 'distinct', Rule::in($this->dtmfDigits())],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'collect_dtmf' => [
                'data' => ['required', 'array:collection_name,interdigit_timeout,max_digits,terminators,timeout,skip_module'],
                'data.collection_name' => ['present', 'nullable', 'string', 'max:128'],
                'data.interdigit_timeout' => ['required', 'integer', 'min:1', 'max:86400000'],
                'data.max_digits' => ['required', 'integer', 'min:1', 'max:128'],
                'data.terminators' => ['required', 'array', 'max:12'],
                'data.terminators.*' => ['required', 'string', 'distinct', Rule::in($this->dtmfDigits())],
                'data.timeout' => ['required', 'integer', 'min:1', 'max:86400000'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'record_call' => [
                'data' => ['required', 'array:action,format,label,record_min_sec,record_on_answer,record_on_bridge,record_sample_rate,should_follow_transfer,time_limit,skip_module'],
                'data.action' => ['required', 'string', Rule::in(['start', 'stop'])],
                'data.format' => ['present', 'nullable', 'string', Rule::in(['mp3', 'wav'])],
                'data.label' => ['present', 'nullable', 'string', 'max:128'],
                'data.record_min_sec' => ['present', 'nullable', 'integer', 'min:0', 'max:10800'],
                'data.record_on_answer' => ['required', 'boolean'],
                'data.record_on_bridge' => ['required', 'boolean'],
                'data.record_sample_rate' => ['present', 'nullable', 'integer', 'min:8000', 'max:192000'],
                'data.should_follow_transfer' => ['required', 'boolean'],
                'data.time_limit' => ['required', 'integer', 'min:5', 'max:10800'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'record_caller' => [
                'data' => ['required', 'array:format,time_limit,skip_module'],
                'data.format' => ['present', 'nullable', 'string', Rule::in(['mp3', 'wav'])],
                'data.time_limit' => ['required', 'integer', 'min:5', 'max:10800'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'send_dtmf' => [
                'data' => ['required', 'array:digits,duration_ms,skip_module'],
                'data.digits' => ['required', 'string', 'min:1', 'max:128'],
                'data.duration_ms' => ['required', 'integer', 'min:1', 'max:60000'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'flush_dtmf' => [
                'data' => ['required', 'array:collection_name,skip_module'],
                'data.collection_name' => ['required', 'string', 'min:1', 'max:128'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'dead_air' => [
                'data' => ['required', 'array:skip_module'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'language' => [
                'data' => ['required', 'array:language,skip_module'],
                'data.language' => ['required', 'string', 'regex:/^[A-Za-z]{2}(?:-[A-Za-z]{2})?$/'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'response' => [
                'data' => ['required', 'array:code,message,skip_module'],
                'data.code' => ['required', 'integer', 'min:400', 'max:699'],
                'data.message' => ['present', 'nullable', 'string', 'max:128'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'hangup' => [
                'data' => ['required', 'array:skip_module'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'set_variable' => [
                'data' => ['required', 'array:variable,value,channel,skip_module'],
                'data.variable' => ['required', 'string', Rule::in(['call_priority'])],
                'data.value' => ['required', 'string', 'regex:/^\d{1,3}$/', 'integer', 'min:0', 'max:255'],
                'data.channel' => ['required', 'string', Rule::in(['a', 'both'])],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'set_variables' => [
                'data' => ['required', 'array:custom_application_vars,export,skip_module'],
                'data.custom_application_vars' => ['required', 'array', 'max:64'],
                'data.custom_application_vars.*' => ['present', 'string', 'max:1024'],
                'data.export' => ['required', 'boolean'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'manual_presence' => [
                'data' => ['required', 'array:presence_id,status,skip_module'],
                'data.presence_id' => ['required', 'string', 'max:256'],
                'data.status' => ['required', 'string', Rule::in(['idle', 'ringing', 'busy'])],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'group_pickup' => [
                'data' => ['required', 'array:target_type,target_id,skip_module'],
                'data.target_type' => ['required', 'string', Rule::in(['extension', 'device', 'group'])],
                'data.target_id' => ['required', 'uuid'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'page_group' => [
                'data' => ['required', 'array:audio,device_ids,skip_module'],
                'data.audio' => ['required', 'string', Rule::in(['one-way', 'two-way'])],
                'data.device_ids' => ['required', 'array', 'min:1', 'max:20'],
                'data.device_ids.*' => ['required', 'uuid', 'distinct:strict'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'ring_group' => [
                'data' => ['required', 'array:strategy,endpoints,repeats,ignore_forward,fail_on_single_reject,ringback_media_id,ringtone_internal,ringtone_external,skip_module'],
                'data.strategy' => ['required', 'string', Rule::in(['simultaneous', 'single', 'weighted_random'])],
                'data.endpoints' => ['required', 'array', 'min:1', 'max:'.RingGroupPolicy::MAX_ENDPOINTS],
                'data.endpoints.*' => ['required', 'array:device_id,delay,timeout,weight'],
                'data.endpoints.*.device_id' => ['required', 'uuid', 'distinct:strict'],
                'data.endpoints.*.delay' => ['required', 'integer', 'min:0', 'max:'.RingGroupPolicy::MAX_ENDPOINT_DELAY],
                'data.endpoints.*.timeout' => ['required', 'integer', 'min:1', 'max:'.RingGroupPolicy::MAX_ENDPOINT_TIMEOUT],
                'data.endpoints.*.weight' => ['nullable', 'integer', 'min:1', 'max:100'],
                'data.repeats' => ['required', 'integer', 'min:1', 'max:'.RingGroupPolicy::MAX_REPEATS],
                'data.ignore_forward' => ['required', $strictBoolean],
                'data.fail_on_single_reject' => ['required', $strictBoolean],
                'data.ringback_media_id' => ['present', 'nullable', 'uuid'],
                'data.ringtone_internal' => ['present', 'nullable', 'string', 'min:1', 'max:256', 'not_regex:/[\x00\r\n]/'],
                'data.ringtone_external' => ['present', 'nullable', 'string', 'min:1', 'max:256', 'not_regex:/[\x00\r\n]/'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'receive_fax' => [
                'data' => ['required', 'array:owner_id,fax_option,skip_module'],
                'data.owner_id' => ['required', 'uuid'],
                'data.fax_option' => [
                    'required',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! is_bool($value) && $value !== 'auto') {
                            $fail('Select automatic, enabled, or disabled T.38 negotiation.');
                        }
                    },
                ],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'conference' => [
                'data' => ['required', 'array:service_mode,skip_module'],
                'data.service_mode' => ['required', 'accepted'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'voicemail' => [
                'data' => ['required', 'array:action,skip_module'],
                'data.action' => ['required', 'string', Rule::in(['check'])],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'branch_variable' => [
                'data' => ['required', 'array:variable,scope,skip_module'],
                'data.variable' => ['required', 'string', Rule::in(['call_priority'])],
                'data.scope' => ['required', 'string', Rule::in(['custom_channel_vars'])],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'branch_bnumber' => [
                'data' => ['required', 'array:hunt,hunt_allow,hunt_deny,skip_module'],
                'data.hunt' => ['required', 'boolean'],
                'data.hunt_allow' => ['present', 'nullable', 'string', 'min:1', 'max:512', new SafeSwitchRegex],
                'data.hunt_deny' => ['present', 'nullable', 'string', 'min:1', 'max:512', new SafeSwitchRegex],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'missed_call_alert' => [
                'data' => ['required', 'array:recipients,skip_module'],
                'data.recipients' => ['required', 'array', 'min:1', 'max:50'],
                'data.recipients.*' => ['required', 'array:type,id'],
                'data.recipients.*.type' => ['required', 'string', Rule::in(['user', 'email'])],
                'data.recipients.*.id' => ['required', 'string', 'max:254'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'set_cid' => [
                'data' => ['required', 'array:caller_id_name,caller_id_number,skip_module'],
                'data.caller_id_name' => ['present', 'string', 'max:128'],
                'data.caller_id_number' => ['present', 'string', 'max:64'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'prepend_cid' => [
                'data' => ['required', 'array:action,apply_to,caller_id_name_prefix,caller_id_number_prefix,skip_module'],
                'data.action' => ['required', 'string', Rule::in(['reset', 'prepend'])],
                'data.apply_to' => ['required', 'string', Rule::in(['original', 'current'])],
                'data.caller_id_name_prefix' => ['present', 'string', 'max:128'],
                'data.caller_id_number_prefix' => ['present', 'string', 'max:64'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'set_alert_info' => [
                'data' => ['required', 'array:alert_info,skip_module'],
                'data.alert_info' => ['required', 'string', 'max:256', 'not_regex:/[\r\n]/'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'check_cid' => [
                'data' => ['required', 'array:regex,use_absolute_mode,external_caller_id_name,external_caller_id_number,user_id,skip_module'],
                'data.regex' => ['required', 'string', 'max:512', new SafeSwitchRegex],
                'data.use_absolute_mode' => [
                    'required',
                    'boolean',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if ($value !== false) {
                            $fail('Absolute caller-number mode is preserved but not editable.');
                        }
                    },
                ],
                'data.external_caller_id_name' => ['present', 'nullable', 'string', 'min:1', 'max:128'],
                'data.external_caller_id_number' => ['present', 'nullable', 'string', 'min:1', 'max:64'],
                'data.user_id' => ['present', 'nullable', 'uuid'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'cidlistmatch' => [
                'data' => ['required', 'array:caller_id_list_id,skip_module'],
                'data.caller_id_list_id' => ['required', 'uuid'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'temporal_route' => [
                'data' => ['required', 'array:action,rules,skip_module'],
                'data.action' => ['required', 'string', Rule::in(['disable', 'enable', 'reset'])],
                'data.rules' => ['present', 'array', 'max:250'],
                'data.rules.*' => ['required', 'uuid', 'distinct'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'ring_group_toggle' => [
                'data' => ['required', 'array:action,callflow_id,skip_module'],
                'data.action' => ['required', 'string', Rule::in(['login', 'logout'])],
                'data.callflow_id' => ['required', 'uuid'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'acdc_queue' => [
                'data' => ['required', 'array:action,queue_id,skip_module'],
                'data.action' => ['required', 'string', Rule::in(['login', 'logout'])],
                'data.queue_id' => ['required', 'uuid'],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'hotdesk' => [
                'data' => ['required', 'array:action,skip_module'],
                'data.action' => ['required', 'string', Rule::in(['login', 'logout', 'toggle'])],
                'data.skip_module' => ['required', 'boolean'],
            ],
            'do_not_disturb' => [
                'data' => ['required', 'array:action,skip_module'],
                'data.action' => ['required', 'string', Rule::in(['activate', 'deactivate', 'toggle'])],
                'data.skip_module' => ['required', 'boolean'],
            ],
            default => throw ValidationException::withMessages([
                'module' => ['This callflow action is not available in the guided editor.'],
            ]),
        };

        /** @var array{data: array<string, mixed>} $validated */
        $validated = Validator::make(['data' => $data], $rules)->validate();

        $settings = $validated['data'];

        if ($module === 'missed_call_alert') {
            $this->validateMissedCallAlertRecipients($settings['recipients']);
        }

        if ($module === 'check_cid') {
            $this->validateCheckCidIdentity($settings);
        }

        if ($module === 'set_variables') {
            $this->validateSetVariables($settings['custom_application_vars']);
        }

        if ($module === 'manual_presence'
            && (trim($settings['presence_id']) !== $settings['presence_id']
                || preg_match('/^[^\s@]+(?:@[^\s@]+)?$/u', $settings['presence_id']) !== 1)) {
            throw ValidationException::withMessages([
                'data.presence_id' => ['Enter a presence ID such as 1001 or 1001@example.com without spaces.'],
            ]);
        }

        if ($module === 'branch_bnumber'
            && $settings['hunt'] !== true
            && ($settings['hunt_allow'] !== null || $settings['hunt_deny'] !== null)) {
            throw ValidationException::withMessages([
                'data.hunt_allow' => ['Hunt filters can only be configured when hunt mode is enabled.'],
                'data.hunt_deny' => ['Hunt filters can only be configured when hunt mode is enabled.'],
            ]);
        }

        if ($module === 'ring_group') {
            $this->validateRingGroup($settings);
        }

        return $settings;
    }

    /** @param array<string, mixed> $settings */
    private function validateRingGroup(array $settings): void
    {
        $errors = [];

        if (in_array($settings['strategy'], ['single', 'weighted_random'], true)) {
            foreach ($settings['endpoints'] as $index => $endpoint) {
                if ($endpoint['delay'] !== 0) {
                    $errors["data.endpoints.$index.delay"] = [
                        'Sequential Ring Group strategies cannot use a delay.',
                    ];
                }
            }
        }

        foreach ($settings['endpoints'] as $index => $endpoint) {
            $weight = $endpoint['weight'] ?? null;

            if ($settings['strategy'] === 'weighted_random' && $weight === null) {
                $errors["data.endpoints.$index.weight"] = [
                    'Enter a weight from 1 through 100 for weighted-random routing.',
                ];
            } elseif ($settings['strategy'] !== 'weighted_random' && $weight !== null) {
                $errors["data.endpoints.$index.weight"] = [
                    'Weights are available only for weighted-random routing.',
                ];
            }
        }

        if (RingGroupPolicy::attemptTimeout($settings['strategy'], $settings['endpoints'])
            > RingGroupPolicy::MAX_ATTEMPT_TIMEOUT) {
            $errors['data.endpoints'] = [
                'Keep the total Ring Group attempt duration within 120 seconds.',
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string|int, mixed> $variables */
    private function validateSetVariables(array $variables): void
    {
        $errors = [];

        foreach ($variables as $key => $value) {
            $name = (string) $key;

            if (preg_match('/^[A-Za-z0-9_-]{1,128}$/', $name) !== 1) {
                $errors["data.custom_application_vars.$name"] = [
                    'Use 1–128 letters, numbers, hyphens, or underscores for the variable name.',
                ];
            }

            if (is_string($value) && preg_match('/[\x00\r\n]/', $value) === 1) {
                $errors["data.custom_application_vars.$name"] = [
                    'Custom application variable values cannot contain line breaks or null bytes.',
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $settings */
    private function validateCheckCidIdentity(array $settings): void
    {
        $fields = ['external_caller_id_name', 'external_caller_id_number', 'user_id'];
        $configured = array_filter($fields, fn (string $field): bool => ($settings[$field] ?? null) !== null);

        if ($configured === [] || count($configured) === count($fields)) {
            return;
        }

        $errors = [];

        foreach ($fields as $field) {
            if (($settings[$field] ?? null) === null) {
                $errors["data.$field"] = ['Complete all caller identity override fields or clear all three.'];
            }
        }

        throw ValidationException::withMessages($errors);
    }

    /** @return list<string> */
    private function dtmfDigits(): array
    {
        return ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '#', '*'];
    }

    /** @param list<array{type: string, id: string}> $recipients */
    private function validateMissedCallAlertRecipients(array $recipients): void
    {
        $validator = Validator::make(
            ['recipients' => $recipients],
            ['recipients.*.id' => [function (string $attribute, mixed $value, \Closure $fail) use ($recipients): void {
                $index = (int) explode('.', $attribute)[1];
                $recipient = $recipients[$index] ?? null;

                if (($recipient['type'] ?? null) === 'user' && (! is_string($value) || ! Str::isUuid($value))) {
                    $fail('Select a synchronized extension.');
                }

                if (($recipient['type'] ?? null) === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $fail('Enter a valid email address.');
                }
            }]],
        );

        $validator->validate();
    }
}
