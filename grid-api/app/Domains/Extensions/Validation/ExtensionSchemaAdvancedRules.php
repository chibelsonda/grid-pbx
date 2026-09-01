<?php

namespace App\Domains\Extensions\Validation;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Switch\MetaflowInputValidator;
use App\Shared\Switch\MetaflowPolicy;
use App\Shared\Validation\Rules\SafeSwitchRegex;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ExtensionSchemaAdvancedRules
{
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

    private const VIDEO_CODECS = ['H261', 'H263', 'H264', 'VP8'];

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'media' => [
                'sometimes',
                'array:audio,video,bypass_media,encryption,fax_option,ignore_early_media,progress_timeout',
            ],
            'media.audio' => ['required_with:media', 'array:codecs'],
            'media.audio.codecs' => ['present_with:media', 'array', 'max:'.count(self::AUDIO_CODECS)],
            'media.audio.codecs.*' => ['string', Rule::in(self::AUDIO_CODECS), 'distinct'],
            'media.video' => ['required_with:media', 'array:codecs'],
            'media.video.codecs' => ['present_with:media', 'array', 'max:'.count(self::VIDEO_CODECS)],
            'media.video.codecs.*' => ['string', Rule::in(self::VIDEO_CODECS), 'distinct'],
            'media.bypass_media' => ['required_with:media', Rule::in([true, false, 'auto'])],
            'media.encryption' => ['required_with:media', 'array:enforce_security,methods'],
            'media.encryption.enforce_security' => ['required_with:media', 'boolean'],
            'media.encryption.methods' => ['present_with:media', 'array', 'max:2'],
            'media.encryption.methods.*' => ['string', Rule::in(['srtp', 'zrtp']), 'distinct'],
            'media.fax_option' => ['required_with:media', 'boolean'],
            'media.ignore_early_media' => ['required_with:media', 'boolean'],
            'media.progress_timeout' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'music_on_hold' => ['sometimes', 'array:media_id,preserve_media'],
            'music_on_hold.media_id' => ['nullable', 'uuid'],
            'music_on_hold.preserve_media' => ['required_with:music_on_hold', 'boolean'],
            'ringtones' => ['sometimes', 'array:internal,external'],
            'ringtones.internal' => ['nullable', 'string', 'max:256'],
            'ringtones.external' => ['nullable', 'string', 'max:256'],
            'dial_plan' => ['sometimes', 'array:system,rules'],
            'dial_plan.system' => ['present_with:dial_plan', 'array', 'max:64'],
            'dial_plan.system.*' => ['string', 'min:1', 'max:255', 'distinct'],
            'dial_plan.rules' => ['present_with:dial_plan', 'array', 'max:64'],
            'dial_plan.rules.*' => ['array:pattern,description,prefix,suffix'],
            'dial_plan.rules.*.pattern' => ['required', 'string', 'max:512', new SafeSwitchRegex],
            'dial_plan.rules.*.description' => ['nullable', 'string', 'max:255'],
            'dial_plan.rules.*.prefix' => ['nullable', 'string', 'max:64'],
            'dial_plan.rules.*.suffix' => ['nullable', 'string', 'max:64'],
            'formatters' => ['sometimes', 'array', 'max:64'],
            'formatters.*' => [
                'array:field,direction,match_invite_format,prefix,regex,strip,suffix,value',
            ],
            'formatters.*.field' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_]+$/'],
            'formatters.*.direction' => ['nullable', Rule::in(['inbound', 'outbound', 'both'])],
            'formatters.*.match_invite_format' => ['required', 'boolean'],
            'formatters.*.prefix' => ['nullable', 'string', 'max:1024'],
            'formatters.*.regex' => ['nullable', 'string', 'max:2048', new SafeSwitchRegex],
            'formatters.*.strip' => ['required', 'boolean'],
            'formatters.*.suffix' => ['nullable', 'string', 'max:1024'],
            'formatters.*.value' => ['nullable', 'string', 'max:1024'],
            'profile' => [
                'sometimes',
                'array:addresses,assistant,birthday,nicknames,note,role,sort_string,title',
            ],
            'profile.addresses' => ['present_with:profile', 'array', 'max:20'],
            'profile.addresses.*' => ['array:address,types'],
            'profile.addresses.*.address' => ['required', 'string', 'max:512'],
            'profile.addresses.*.types' => ['required', 'array', 'max:7'],
            'profile.addresses.*.types.*' => [
                'string',
                Rule::in(['dom', 'postal', 'intl', 'parcel', 'home', 'work', 'pref']),
                'distinct',
            ],
            'profile.assistant' => ['nullable', 'string', 'max:255'],
            'profile.birthday' => ['nullable', 'string', 'max:64'],
            'profile.nicknames' => ['present_with:profile', 'array', 'max:20'],
            'profile.nicknames.*' => ['string', 'min:1', 'max:255', 'distinct'],
            'profile.note' => ['nullable', 'string', 'max:2000'],
            'profile.role' => ['nullable', 'string', 'max:255'],
            'profile.sort_string' => ['nullable', 'string', 'max:255'],
            'profile.title' => ['nullable', 'string', 'max:255'],
            'pronounced_name' => ['sometimes', 'array:media_id,preserve_media'],
            'pronounced_name.media_id' => ['nullable', 'uuid'],
            'pronounced_name.preserve_media' => ['required_with:pronounced_name', 'boolean'],
            'metaflows' => ['sometimes', 'array:binding_digit,digit_timeout,listen_on,actions'],
            'metaflows.binding_digit' => [
                'nullable',
                'string',
                Rule::in(['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '*', '#']),
            ],
            'metaflows.digit_timeout' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'metaflows.listen_on' => ['nullable', Rule::in(['both', 'self', 'peer'])],
            'metaflows.actions' => ['present_with:metaflows', 'array', 'max:50'],
            'metaflows.actions.*' => ['array:trigger_type,trigger,module,data,children'],
            'metaflows.actions.*.trigger_type' => ['required', Rule::in(['number', 'pattern'])],
            'metaflows.actions.*.trigger' => ['required', 'string', 'max:255'],
            'metaflows.actions.*.module' => [
                'required',
                Rule::in(array_keys(MetaflowPolicy::EDITABLE_MODULE_FIELDS)),
            ],
            'metaflows.actions.*.data' => ['present', 'array', 'max:20'],
            'metaflows.actions.*.children' => ['sometimes', 'array', 'max:20'],
        ];
    }

    /** @param array<string, mixed> $data */
    public static function validate(
        Validator $validator,
        array $data,
        ?SwitchAccount $account,
    ): void {
        $patterns = collect(data_get($data, 'dial_plan.rules', []))
            ->pluck('pattern')
            ->filter(fn (mixed $pattern): bool => is_string($pattern));

        if ($patterns->unique()->count() !== $patterns->count()) {
            $validator->errors()->add(
                'dial_plan.rules',
                'Dial-plan patterns must be unique.',
            );
        }

        if ($account !== null) {
            app(MetaflowInputValidator::class)->validate(
                $validator,
                (array) data_get($data, 'metaflows.actions', []),
                $account,
            );
        }
    }
}
