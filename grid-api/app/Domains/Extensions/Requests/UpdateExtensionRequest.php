<?php

namespace App\Domains\Extensions\Requests;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $accountId = $this->accountInternalId();
        $extensionId = $this->extensionInternalId($accountId);

        return [
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
            'voicemail.notification_emails' => ['required', 'array', 'max:10'],
            'voicemail.notification_emails.*' => ['email:rfc', 'max:254', 'distinct'],
            'voicemail.transcribe' => ['required', 'boolean'],
            'voicemail.require_pin' => ['required', 'boolean'],
            'voicemail.pin' => ['nullable', 'string', 'regex:/^[0-9]{4,6}$/'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
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
        return SwitchAccount::query()
            ->where('id', (string) $this->route('account'))
            ->value('account_id');
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
