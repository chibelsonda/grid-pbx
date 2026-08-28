<?php

namespace App\Domains\Devices\Requests;

use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDeviceRequest extends FormRequest
{
    /** @var list<string> */
    private const DEVICE_TYPES = [
        'sip_device',
        'cellphone',
        'smartphone',
        'softphone',
        'landline',
        'fax',
        'ata',
        'sip_uri',
    ];

    /** @var list<string> */
    private const AUDIO_CODECS = [
        'OPUS',
        'CELT@32000h',
        'G7221@32000h',
        'G7221@16000h',
        'G722',
        'speex@32000h',
        'speex@16000h',
        'PCMU',
        'PCMA',
        'G729',
        'GSM',
        'CELT@48000h',
        'CELT@64000h',
        'G722_16',
        'G722_32',
        'CELT_48',
        'CELT_64',
        'Speex',
        'speex',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'device_type' => ['required', 'string', Rule::in(self::DEVICE_TYPES)],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'mac_address' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/',
            ],
            'is_enabled' => ['required', 'boolean'],
            'assigned_extension_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('switch_extensions', 'id')
                    ->where('switch_account_id', $this->accountInternalId()),
            ],
            'sip_username' => ['nullable', 'string', 'min:2', 'max:32'],
            'sip_password' => ['nullable', 'string', 'min:12', 'max:32'],
            'provision' => ['sometimes', 'array:endpoint_brand,endpoint_family,endpoint_model'],
            'provision.endpoint_brand' => ['nullable', 'string', 'max:255'],
            'provision.endpoint_family' => ['nullable', 'string', 'max:255'],
            'provision.endpoint_model' => ['nullable', 'string', 'max:255'],
            'call_forward' => [
                'sometimes',
                'array:enabled,number,direct_calls_only,failover,ignore_early_media,keep_caller_id,require_keypress,substitute',
            ],
            'call_forward.enabled' => ['sometimes', 'boolean'],
            'call_forward.number' => ['nullable', 'string', 'max:15'],
            'call_forward.direct_calls_only' => ['sometimes', 'boolean'],
            'call_forward.failover' => ['sometimes', 'boolean'],
            'call_forward.ignore_early_media' => ['sometimes', 'boolean'],
            'call_forward.keep_caller_id' => ['sometimes', 'boolean'],
            'call_forward.require_keypress' => ['sometimes', 'boolean'],
            'call_forward.substitute' => ['sometimes', 'boolean'],
            'sip' => [
                'sometimes',
                'array:method,username,password,realm,expire_seconds,invite_format,ip,number,route,static_route,ignore_completed_elsewhere,custom_sip_headers',
            ],
            'sip.method' => ['sometimes', 'string', Rule::in(['password', 'ip'])],
            'sip.username' => ['nullable', 'string', 'min:2', 'max:32'],
            'sip.password' => ['nullable', 'string', 'min:12', 'max:32'],
            'sip.realm' => ['nullable', 'string', 'min:4', 'max:253', 'regex:/^[.\w_-]+$/'],
            'sip.expire_seconds' => ['nullable', 'integer', 'min:30', 'max:86400'],
            'sip.invite_format' => [
                'sometimes',
                'string',
                Rule::in(['username', 'npan', '1npan', 'e164', 'route', 'contact']),
            ],
            'sip.ip' => ['nullable', 'ip'],
            'sip.number' => ['nullable', 'string', 'max:64'],
            'sip.route' => ['nullable', 'string', 'max:2048'],
            'sip.static_route' => ['nullable', 'string', 'max:2048'],
            'sip.ignore_completed_elsewhere' => ['sometimes', 'boolean'],
            'sip.custom_sip_headers' => ['sometimes', 'array:in,out'],
            'sip.custom_sip_headers.in' => ['sometimes', 'array', 'max:50'],
            'sip.custom_sip_headers.out' => ['sometimes', 'array', 'max:50'],
            'sip.custom_sip_headers.in.*' => ['array:name,value'],
            'sip.custom_sip_headers.out.*' => ['array:name,value'],
            'sip.custom_sip_headers.in.*.name' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/', 'not_regex:/(?:authorization|cookie|password|secret|token|api[-_]?key|pin)$/i'],
            'sip.custom_sip_headers.out.*.name' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/', 'not_regex:/(?:authorization|cookie|password|secret|token|api[-_]?key|pin)$/i'],
            'sip.custom_sip_headers.in.*.value' => ['required', 'string', 'max:1024'],
            'sip.custom_sip_headers.out.*.value' => ['required', 'string', 'max:1024'],
            'media' => [
                'sometimes',
                'array:audio,video,bypass_media,encryption,fax_option,ignore_early_media,progress_timeout',
            ],
            'media.audio' => ['sometimes', 'array:codecs'],
            'media.audio.codecs' => ['sometimes', 'array', 'max:19'],
            'media.audio.codecs.*' => ['string', 'distinct:strict', Rule::in(self::AUDIO_CODECS)],
            'media.video' => ['sometimes', 'array:codecs'],
            'media.video.codecs' => ['sometimes', 'array', 'max:4'],
            'media.video.codecs.*' => [
                'string',
                'distinct:strict',
                Rule::in(['H261', 'H263', 'H264', 'VP8']),
            ],
            'media.bypass_media' => ['nullable', Rule::in([true, false, 'auto'])],
            'media.encryption' => ['sometimes', 'array:enforce_security,methods'],
            'media.encryption.enforce_security' => ['sometimes', 'boolean'],
            'media.encryption.methods' => ['sometimes', 'array', 'max:2'],
            'media.encryption.methods.*' => [
                'string',
                'distinct:strict',
                Rule::in(['zrtp', 'srtp']),
            ],
            'media.fax_option' => ['sometimes', 'boolean'],
            'media.ignore_early_media' => ['sometimes', 'boolean'],
            'media.progress_timeout' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'caller_id' => ['sometimes', 'array:internal,external,emergency,asserted'],
            'caller_id.internal' => ['sometimes', 'array:name,number'],
            'caller_id.internal.name' => ['nullable', 'string', 'max:35'],
            'caller_id.internal.number' => ['nullable', 'string', 'max:35'],
            'caller_id.external' => ['sometimes', 'array:name,number'],
            'caller_id.external.name' => ['nullable', 'string', 'max:35'],
            'caller_id.external.number' => ['nullable', 'string', 'max:35'],
            'caller_id.emergency' => ['sometimes', 'array:name,number'],
            'caller_id.emergency.name' => ['nullable', 'string', 'max:35'],
            'caller_id.emergency.number' => ['nullable', 'string', 'max:35'],
            'caller_id.asserted' => ['sometimes', 'array:name,number,realm'],
            'caller_id.asserted.name' => ['nullable', 'string', 'max:35'],
            'caller_id.asserted.number' => ['nullable', 'string', 'max:35'],
            'caller_id.asserted.realm' => ['nullable', 'string', 'max:253'],
            'caller_id_options' => ['sometimes', 'array:outbound_privacy'],
            'caller_id_options.outbound_privacy' => [
                'nullable',
                'string',
                Rule::in(['full', 'name', 'number', 'none']),
            ],
            'call_waiting' => ['sometimes', 'array:enabled'],
            'call_waiting.enabled' => ['sometimes', 'boolean'],
            'do_not_disturb' => ['sometimes', 'array:enabled'],
            'do_not_disturb.enabled' => ['sometimes', 'boolean'],
            'contact_list' => ['sometimes', 'array:exclude'],
            'contact_list.exclude' => ['sometimes', 'boolean'],
            'exclude_from_queues' => ['sometimes', 'boolean'],
            'language' => ['nullable', 'string', 'max:32'],
            'timezone' => ['nullable', 'timezone'],
            'presence_id' => ['nullable', 'string', 'max:255'],
            'mwi_unsolicited_updates' => ['sometimes', 'boolean'],
            'register_overwrite_notify' => ['sometimes', 'boolean'],
            'suppress_unregister_notifications' => ['sometimes', 'boolean'],
            'ringtones' => ['sometimes', 'array:internal,external'],
            'ringtones.internal' => ['nullable', 'string', 'max:256'],
            'ringtones.external' => ['nullable', 'string', 'max:256'],
            'call_restriction' => ['sometimes', 'array', 'max:100'],
            'call_restriction.*' => ['array:action'],
            'call_restriction.*.action' => ['required', 'string', Rule::in(['inherit', 'deny'])],
            'call_recording' => ['sometimes', 'array:any,inbound,outbound'],
            'music_on_hold' => ['sometimes', 'array:media_id'],
            'music_on_hold.media_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('switch_media', 'id')
                    ->where('switch_account_id', $this->accountInternalId()),
            ],
            'outbound_flags' => ['sometimes', 'array:static,dynamic'],
            'outbound_flags.static' => ['sometimes', 'array', 'max:64'],
            'outbound_flags.dynamic' => ['sometimes', 'array', 'max:64'],
            'outbound_flags.static.*' => ['string', 'distinct:strict', 'max:255'],
            'outbound_flags.dynamic.*' => ['string', 'distinct:strict', 'max:255'],
            'dial_plan' => ['sometimes', 'array:system,rules'],
            'dial_plan.system' => ['sometimes', 'array', 'max:64'],
            'dial_plan.system.*' => ['string', 'distinct:strict', 'max:255'],
            'dial_plan.rules' => ['sometimes', 'array', 'max:64'],
            'dial_plan.rules.*' => ['array:pattern,description,prefix,suffix'],
            'dial_plan.rules.*.pattern' => ['required', 'string', 'distinct:strict', 'max:512'],
            'dial_plan.rules.*.description' => ['nullable', 'string', 'max:255'],
            'dial_plan.rules.*.prefix' => ['nullable', 'string', 'max:64'],
            'dial_plan.rules.*.suffix' => ['nullable', 'string', 'max:64'],
            'metaflows' => ['sometimes', 'array:binding_digit,digit_timeout,listen_on'],
            'metaflows.binding_digit' => ['nullable', 'string', Rule::in(['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '*', '#'])],
            'metaflows.digit_timeout' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'metaflows.listen_on' => ['nullable', 'string', Rule::in(['both', 'self', 'peer'])],
        ];

        foreach (['any', 'inbound', 'outbound'] as $direction) {
            $rules["call_recording.{$direction}"] = ['sometimes', 'array:any,onnet,offnet'];

            foreach (['any', 'onnet', 'offnet'] as $network) {
                $path = "call_recording.{$direction}.{$network}";
                $rules[$path] = [
                    'sometimes',
                    'array:enabled,format,record_min_sec,record_on_answer,record_on_bridge,record_sample_rate,time_limit',
                ];
                $rules["{$path}.enabled"] = ['sometimes', 'boolean'];
                $rules["{$path}.format"] = ['sometimes', 'string', Rule::in(['mp3', 'wav'])];
                $rules["{$path}.record_min_sec"] = ['nullable', 'integer', 'min:0', 'max:3600'];
                $rules["{$path}.record_on_answer"] = ['sometimes', 'boolean'];
                $rules["{$path}.record_on_bridge"] = ['sometimes', 'boolean'];
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

    private function accountInternalId(): ?string
    {
        return SwitchAccount::query()
            ->where('id', (string) $this->route('account'))
            ->value('account_id');
    }
}
