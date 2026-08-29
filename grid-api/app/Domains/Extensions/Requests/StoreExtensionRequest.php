<?php

namespace App\Domains\Extensions\Requests;

use App\Domains\Devices\Requests\SaveDeviceRequest;
use App\Domains\Devices\Support\MacAddress;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Requests\SaveVoicemailBoxRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $accountId = $this->accountInternalId();

        return [
            'first_name' => ['required', 'string', 'max:128'],
            'last_name' => ['required', 'string', 'max:128'],
            'extension' => [
                'required',
                'string',
                'regex:/^[0-9]{2,15}$/',
                Rule::unique('switch_extensions', 'extension')
                    ->where('switch_account_id', $accountId)
                    ->whereNull('deleted_at'),
            ],
            'username' => [
                'nullable',
                'string',
                'max:256',
                'regex:/^[+@.\w_-]+$/',
                Rule::unique('switch_extensions', 'username')
                    ->where('switch_account_id', $accountId)
                    ->whereNull('deleted_at'),
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
                'required_if:hotdesk.require_pin,true',
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
            'device' => ['required', 'array:enabled,input'],
            'device.enabled' => ['required', 'boolean'],
            'device.input' => [
                Rule::requiredIf($this->boolean('device.enabled')),
                Rule::prohibitedIf(! $this->boolean('device.enabled')),
                'nullable',
                'array',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $macAddress = $this->input('device.input.mac_address');

        if (! is_string($macAddress)) {
            return;
        }

        $device = (array) $this->input('device', []);
        $deviceInput = (array) ($device['input'] ?? []);
        $deviceInput['mac_address'] = MacAddress::canonicalize($macAddress);
        $device['input'] = $deviceInput;

        $this->merge(['device' => $device]);
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateVoicemailInput($validator);
            $this->validateDeviceInput($validator);

            $username = $this->input('username');
            $password = $this->input('password');
            $hasUsername = is_string($username) && $username !== '';
            $hasPassword = is_string($password) && $password !== '';

            if ($hasUsername && ! $hasPassword) {
                $validator->errors()->add(
                    'password',
                    'Enter a password when enabling a Switch user login.',
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

            if ($this->boolean('clear_credentials')) {
                $validator->errors()->add(
                    'clear_credentials',
                    'A new Switch user does not have login credentials to remove.',
                );
            }

            if ($this->boolean('hotdesk.clear_pin')) {
                $validator->errors()->add(
                    'hotdesk.clear_pin',
                    'A new hotdesk profile does not have a PIN to remove.',
                );
            }
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
        $voicemailRequest = SaveVoicemailBoxRequest::create($this->getUri(), 'POST', $input);
        $voicemailRequest->setContainer($this->container);
        $voicemailRequest->setRouteResolver($this->getRouteResolver());
        $voicemailRequest->setUserResolver($this->getUserResolver());
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

    private function validateDeviceInput(Validator $validator): void
    {
        if (! $this->boolean('device.enabled')) {
            return;
        }

        $input = $this->input('device.input');

        if (! is_array($input)) {
            return;
        }

        /** @var SaveDeviceRequest $deviceRequest */
        $deviceRequest = SaveDeviceRequest::create($this->getUri(), 'POST', $input);
        $deviceRequest->setContainer($this->container);
        $deviceRequest->setRouteResolver($this->getRouteResolver());
        $deviceRequest->setUserResolver($this->getUserResolver());
        $deviceRequest->replace($input);

        $deviceValidator = ValidatorFacade::make(
            $input,
            $this->container->call([$deviceRequest, 'rules']),
        );

        foreach ($deviceRequest->after() as $callback) {
            $deviceValidator->after($callback);
        }

        if ($deviceValidator->fails()) {
            foreach ($deviceValidator->errors()->messages() as $path => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add("device.input.{$path}", $message);
                }
            }

            return;
        }

        $device = (array) $this->input('device', []);
        $device['input'] = $deviceValidator->validated();
        $this->merge(['device' => $device]);
    }

    private function accountInternalId(): ?string
    {
        return SwitchAccount::query()
            ->where('id', (string) $this->route('account'))
            ->value('account_id');
    }
}
