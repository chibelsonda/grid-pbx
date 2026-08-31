<?php

namespace App\Domains\Extensions\Requests;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Extensions\Validation\ExtensionCoreAdvancedRules;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Requests\SaveVoicemailBoxRequest;
use App\Shared\Switch\MetaflowInputValidator;
use App\Shared\Switch\MetaflowPolicy;
use App\Shared\Validation\Rules\SafeSwitchRegex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
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
            'voicemail' => ['required', 'array:enabled,input'],
            'voicemail.enabled' => ['required', 'boolean'],
            'voicemail.input' => [
                Rule::requiredIf($this->boolean('voicemail.enabled')),
                Rule::prohibitedIf(! $this->boolean('voicemail.enabled')),
                'nullable',
                'array',
            ],
        ];

        return array_merge($rules, ExtensionCoreAdvancedRules::rules());
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateVoicemailInput($validator);

            ExtensionCoreAdvancedRules::validate($validator, $this->all());

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

    private function validateVoicemailInput(Validator $validator): void
    {
        if (! $this->boolean('voicemail.enabled')) {
            return;
        }

        $input = $this->input('voicemail.input');

        if (! is_array($input)) {
            return;
        }

        /** @var SaveVoicemailBoxRequest $voicemailRequest */
        $voicemailRequest = SaveVoicemailBoxRequest::create($this->getUri(), 'PUT', $input);
        $voicemailRequest->setContainer($this->container);
        $voicemailRequest->setUserResolver($this->getUserResolver());
        $voicemailRequest->setRouteResolver(function () {
            $route = clone $this->route();
            $route->setParameter('voicemailBox', $this->managedVoicemailPublicId());

            return $route;
        });
        $voicemailRequest->replace($input);

        $voicemailValidator = ValidatorFacade::make(
            $input,
            $this->container->call([$voicemailRequest, 'rules']),
        );

        foreach ($voicemailRequest->after() as $callback) {
            $voicemailValidator->after($callback);
        }

        if ($voicemailValidator->fails()) {
            foreach ($voicemailValidator->errors()->messages() as $path => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add("voicemail.input.{$path}", $message);
                }
            }

            return;
        }

        $voicemail = (array) $this->input('voicemail', []);
        $voicemail['input'] = $voicemailValidator->validated();
        $this->merge(['voicemail' => $voicemail]);
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

    private function managedVoicemailPublicId(): ?string
    {
        return SwitchExtension::query()
            ->where('switch_account_id', $this->accountInternalId())
            ->where('id', (string) $this->route('extension'))
            ->first()
            ?->voicemailBoxes()
            ->where('is_managed', true)
            ->value('id');
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
