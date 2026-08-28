<?php

namespace App\Domains\Extensions\Requests;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'timezone' => ['nullable', 'timezone'],
            'is_enabled' => ['required', 'boolean'],
            'voicemail.enabled' => ['required', 'boolean'],
            'voicemail.notification_emails' => ['required', 'array', 'max:10'],
            'voicemail.notification_emails.*' => ['email:rfc', 'max:254', 'distinct'],
            'voicemail.transcribe' => ['required', 'boolean'],
            'voicemail.require_pin' => ['required', 'boolean'],
            'voicemail.pin' => ['nullable', 'string', 'regex:/^[0-9]{4,6}$/'],
        ];
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
}
