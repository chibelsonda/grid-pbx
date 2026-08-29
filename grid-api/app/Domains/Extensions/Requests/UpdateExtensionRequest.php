<?php

namespace App\Domains\Extensions\Requests;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Switch\MetaflowInputValidator;
use App\Shared\Switch\MetaflowPolicy;
use App\Shared\Validation\Rules\SafeSwitchRegex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateExtensionRequest extends FormRequest
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

    private bool $accountResolved = false;

    private ?SwitchAccount $resolvedAccount = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $accountId = $this->accountInternalId();
        $extensionId = $this->extensionInternalId($accountId);

        $rules = [
            'first_name' => ['required', 'string', 'max:128'],
            'last_name' => ['required', 'string', 'max:128'],
            'extension' => [
                'required',
                'string',
                'regex:/^[0-9]{2,15}$/',
                Rule::unique('switch_extensions', 'extension')
                    ->where('switch_account_id', $accountId)
                    ->whereNull('deleted_at')
                    ->ignore($extensionId, 'extension_id'),
            ],
            'username' => [
                'nullable',
                'string',
                'max:256',
                'regex:/^[+@.\w_-]+$/',
                Rule::unique('switch_extensions', 'username')
                    ->where('switch_account_id', $accountId)
                    ->whereNull('deleted_at')
                    ->ignore($extensionId, 'extension_id'),
            ],
            'password' => [
                'nullable',
                'string',
                'min:6',
                'max:256',
                'confirmed',
                Rule::prohibitedIf($this->boolean('clear_credentials')),
            ],
            'require_password_update' => ['required', 'boolean'],
            'clear_credentials' => ['required', 'boolean'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'timezone' => ['nullable', 'timezone'],
            'is_enabled' => ['required', 'boolean'],
            'language' => ['nullable', 'string', 'max:32'],
            'presence_id' => ['nullable', 'string', 'max:255'],
            'call_waiting' => ['sometimes', 'array:enabled'],
            'call_waiting.enabled' => ['sometimes', 'boolean'],
            'do_not_disturb' => ['sometimes', 'array:enabled'],
            'do_not_disturb.enabled' => ['sometimes', 'boolean'],
            'contact_list' => ['sometimes', 'array:exclude'],
            'contact_list.exclude' => ['sometimes', 'boolean'],
            'caller_id_options' => ['sometimes', 'array:outbound_privacy'],
            'caller_id_options.outbound_privacy' => [
                'sometimes',
                'string',
                Rule::in(['full', 'name', 'number', 'none']),
            ],
            'caller_id' => ['sometimes', 'array:internal,external,emergency'],
            'caller_id.internal' => ['required_with:caller_id', 'array:name,number'],
            'caller_id.internal.name' => ['nullable', 'string', 'max:35'],
            'caller_id.internal.number' => ['nullable', 'string', 'max:35'],
            'caller_id.external' => ['required_with:caller_id', 'array:name,phone_number_id,preserve_number'],
            'caller_id.external.name' => ['nullable', 'string', 'max:35'],
            'caller_id.external.phone_number_id' => ['nullable', 'uuid'],
            'caller_id.external.preserve_number' => ['required_with:caller_id', 'boolean'],
            'caller_id.emergency' => ['required_with:caller_id', 'array:name,phone_number_id,preserve_number'],
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
            'call_recording' => ['sometimes', 'array:account,endpoint'],
            'media' => [
                'sometimes',
                'array:audio,video,bypass_media,encryption,fax_option,ignore_early_media,progress_timeout',
            ],
            'media.audio' => ['required_with:media', 'array:codecs'],
            'media.audio.codecs' => ['required_with:media', 'array', 'max:'.count(self::AUDIO_CODECS)],
            'media.audio.codecs.*' => ['string', Rule::in(self::AUDIO_CODECS), 'distinct'],
            'media.video' => ['required_with:media', 'array:codecs'],
            'media.video.codecs' => ['required_with:media', 'array', 'max:'.count(self::VIDEO_CODECS)],
            'media.video.codecs.*' => ['string', Rule::in(self::VIDEO_CODECS), 'distinct'],
            'media.bypass_media' => ['required_with:media', Rule::in([true, false, 'auto'])],
            'media.encryption' => ['required_with:media', 'array:enforce_security,methods'],
            'media.encryption.enforce_security' => ['required_with:media', 'boolean'],
            'media.encryption.methods' => ['required_with:media', 'array', 'max:2'],
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
            'dial_plan.system' => ['required_with:dial_plan', 'array', 'max:64'],
            'dial_plan.system.*' => ['string', 'min:1', 'max:255', 'distinct'],
            'dial_plan.rules' => ['required_with:dial_plan', 'array', 'max:64'],
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
            'profile.addresses' => ['required_with:profile', 'array', 'max:20'],
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
            'profile.nicknames' => ['required_with:profile', 'array', 'max:20'],
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
            'metaflows.actions' => ['required_with:metaflows', 'array', 'max:50'],
            'metaflows.actions.*' => ['array:trigger_type,trigger,module,data,children'],
            'metaflows.actions.*.trigger_type' => ['required', Rule::in(['number', 'pattern'])],
            'metaflows.actions.*.trigger' => ['required', 'string', 'max:255'],
            'metaflows.actions.*.module' => [
                'required',
                Rule::in(array_keys(MetaflowPolicy::EDITABLE_MODULE_FIELDS)),
            ],
            'metaflows.actions.*.data' => ['present', 'array', 'max:20'],
            'metaflows.actions.*.children' => ['sometimes', 'array', 'max:20'],
            'hotdesk' => ['required', 'array:enabled,id,keep_logged_in_elsewhere,require_pin,pin,clear_pin'],
            'hotdesk.enabled' => ['required', 'boolean'],
            'hotdesk.id' => [
                'nullable',
                'required_if:hotdesk.enabled,true',
                'string',
                'regex:/^[0-9+#*]{4,15}$/',
            ],
            'hotdesk.keep_logged_in_elsewhere' => ['required', 'boolean'],
            'hotdesk.require_pin' => ['required', 'boolean'],
            'hotdesk.pin' => [
                'nullable',
                'string',
                'regex:/^[0-9]{4,15}$/',
                Rule::prohibitedIf($this->boolean('hotdesk.clear_pin')),
            ],
            'hotdesk.clear_pin' => ['required', 'boolean'],
            'voicemail' => ['required', 'array:enabled,notification_emails,transcribe,require_pin,pin'],
            'voicemail.enabled' => ['required', 'boolean'],
            'voicemail.notification_emails' => ['present', 'array', 'max:10'],
            'voicemail.notification_emails.*' => ['email:rfc', 'max:254', 'distinct'],
            'voicemail.transcribe' => ['required', 'boolean'],
            'voicemail.require_pin' => ['required', 'boolean'],
            'voicemail.pin' => ['nullable', 'string', 'regex:/^[0-9]{4,6}$/'],
        ];

        foreach (['account', 'endpoint'] as $target) {
            foreach (['any', 'inbound', 'outbound'] as $direction) {
                $rules["call_recording.{$target}.{$direction}"] = ['required_with:call_recording', 'array:any,onnet,offnet'];

                foreach (['any', 'onnet', 'offnet'] as $network) {
                    $path = "call_recording.{$target}.{$direction}.{$network}";
                    $rules[$path] = [
                        'required_with:call_recording',
                        'array:enabled,format,record_min_sec,record_on_answer,record_on_bridge,record_sample_rate,time_limit',
                    ];
                    $rules["{$path}.enabled"] = ['required_with:call_recording', 'boolean'];
                    $rules["{$path}.format"] = ['required_with:call_recording', Rule::in(['mp3', 'wav'])];
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
        }

        return $rules;
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $restrictions = $this->input('call_restriction', []);

            if (is_array($restrictions)) {
                foreach (array_keys($restrictions) as $classification) {
                    if (! is_string($classification)
                        || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $classification) !== 1) {
                        $validator->errors()->add(
                            'call_restriction',
                            'A call restriction contains an invalid classification key.',
                        );

                        break;
                    }
                }
            }

            $patterns = collect($this->input('dial_plan.rules', []))
                ->pluck('pattern')
                ->filter(fn (mixed $pattern): bool => is_string($pattern));

            if ($patterns->unique()->count() !== $patterns->count()) {
                $validator->errors()->add(
                    'dial_plan.rules',
                    'Dial-plan patterns must be unique.',
                );
            }

            $account = $this->accountModel();

            if ($account !== null) {
                app(MetaflowInputValidator::class)->validate(
                    $validator,
                    (array) $this->input('metaflows.actions', []),
                    $account,
                );
            }

            $currentUsername = $this->projectedUsername();
            $username = $this->input('username');
            $password = $this->input('password');
            $hasUsername = is_string($username) && $username !== '';
            $hasPassword = is_string($password) && $password !== '';
            $usernameChanged = $hasUsername
                && mb_strtolower($username) !== mb_strtolower($currentUsername ?? '');

            if ($usernameChanged && ! $hasPassword) {
                $validator->errors()->add(
                    'password',
                    'Enter a password when creating or changing the Switch user login.',
                );
            }

            if ($hasPassword && ! $hasUsername) {
                $validator->errors()->add(
                    'username',
                    'Enter a username when setting a Switch user password.',
                );
            }

            if ($this->boolean('require_password_update') && ! $hasUsername) {
                $validator->errors()->add(
                    'require_password_update',
                    'A password update can only be required for a user with login credentials.',
                );
            }

            if ($currentUsername !== null && ! $hasUsername && ! $this->boolean('clear_credentials')) {
                $validator->errors()->add(
                    'username',
                    'Use Remove login credentials to confirm that this Switch login should be deleted.',
                );
            }

            if ($this->boolean('clear_credentials')) {
                if ($currentUsername === null) {
                    $validator->errors()->add(
                        'clear_credentials',
                        'This Switch user does not have login credentials to remove.',
                    );
                }

                if ($hasUsername) {
                    $validator->errors()->add(
                        'clear_credentials',
                        'A username cannot be retained while removing login credentials.',
                    );
                }
            }

            if (! $this->boolean('hotdesk.require_pin')) {
                return;
            }

            if ($this->boolean('hotdesk.clear_pin')) {
                $validator->errors()->add(
                    'hotdesk.clear_pin',
                    'Disable PIN protection before removing the hotdesk PIN.',
                );

                return;
            }

            $pin = $this->input('hotdesk.pin');

            if ((is_string($pin) && $pin !== '') || $this->projectedHotdeskPinConfigured()) {
                return;
            }

            $validator->errors()->add(
                'hotdesk.pin',
                'Enter a hotdesk PIN when PIN protection is enabled.',
            );
        }];
    }

    private function accountInternalId(): ?string
    {
        return $this->accountModel()?->getKey();
    }

    private function accountModel(): ?SwitchAccount
    {
        if ($this->accountResolved) {
            return $this->resolvedAccount;
        }

        $this->accountResolved = true;

        return $this->resolvedAccount = SwitchAccount::query()
            ->where('id', (string) $this->route('account'))
            ->first();
    }

    private function extensionInternalId(?string $accountId): ?string
    {
        return SwitchExtension::query()
            ->where('switch_account_id', $accountId)
            ->where('id', (string) $this->route('extension'))
            ->value('extension_id');
    }

    private function projectedHotdeskPinConfigured(): bool
    {
        $switchJson = SwitchExtension::query()
            ->where('switch_account_id', $this->accountInternalId())
            ->where('id', (string) $this->route('extension'))
            ->value('switch_json');

        if (is_string($switchJson)) {
            $switchJson = json_decode($switchJson, true);
        }

        $pin = is_array($switchJson) ? data_get($switchJson, 'hotdesk.pin') : null;

        return is_string($pin) && $pin !== '';
    }

    private function projectedUsername(): ?string
    {
        $username = SwitchExtension::query()
            ->where('switch_account_id', $this->accountInternalId())
            ->where('id', (string) $this->route('extension'))
            ->value('username');

        return is_string($username) && $username !== '' ? $username : null;
    }
}
