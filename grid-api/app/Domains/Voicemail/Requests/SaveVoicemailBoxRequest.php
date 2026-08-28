<?php

namespace App\Domains\Voicemail\Requests;

use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVoicemailBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'mailbox' => ['required', 'string', 'regex:/^\d{1,30}$/'],
            'assigned_extension_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('switch_extensions', 'id')
                    ->where('switch_account_id', $this->accountInternalId()),
            ],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone:all'],
            'notification_emails' => ['present', 'array', 'max:10'],
            'notification_emails.*' => ['required', 'email:rfc', 'max:255', 'distinct'],
            'transcribe' => ['required', 'boolean'],
            'require_pin' => ['required', 'boolean'],
            'pin' => ['nullable', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }

    private function accountInternalId(): ?string
    {
        return SwitchAccount::query()
            ->where('id', (string) $this->route('account'))
            ->value('account_id');
    }
}
