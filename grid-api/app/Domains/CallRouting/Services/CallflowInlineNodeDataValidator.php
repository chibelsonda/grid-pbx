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
            'branch_variable' => [
                'data' => ['required', 'array:variable,scope,skip_module'],
                'data.variable' => ['required', 'string', Rule::in(['call_priority'])],
                'data.scope' => ['required', 'string', Rule::in(['custom_channel_vars'])],
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
            'call_forward' => [
                'data' => ['required', 'array:action,skip_module'],
                'data.action' => ['required', 'string', Rule::in(['activate', 'deactivate', 'update'])],
                'data.skip_module' => ['required', 'boolean'],
            ],
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

        return $settings;
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
