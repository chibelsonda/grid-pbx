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
            'timezone' => ['nullable', 'string', 'min:5', 'max:32', 'timezone:all'],
            'notification_emails' => ['present', 'array', 'max:10'],
            'notification_emails.*' => ['required', 'email:rfc', 'max:254', 'distinct'],
            'transcribe' => ['required', 'boolean'],
            'require_pin' => ['required', 'boolean'],
            'pin' => ['nullable', 'string', 'regex:/^\d{4,6}$/'],
            'check_if_owner' => ['sometimes', 'boolean'],
            'delete_after_notify' => ['sometimes', 'boolean'],
            'include_message_on_notify' => ['sometimes', 'boolean'],
            'include_transcription_on_notify' => ['sometimes', 'boolean'],
            'media_extension' => ['sometimes', 'string', Rule::in(['mp3', 'mp4', 'wav'])],
            'not_configurable' => ['sometimes', 'boolean'],
            'oldest_message_first' => ['sometimes', 'boolean'],
            'save_after_notify' => ['sometimes', 'boolean'],
            'skip_envelope' => ['sometimes', 'boolean'],
            'skip_greeting' => ['sometimes', 'boolean'],
            'skip_instructions' => ['sometimes', 'boolean'],
            'is_voicemail_ff_rw_enabled' => ['sometimes', 'boolean'],
            'seek_duration_ms' => ['sometimes', 'integer', 'min:0', 'max:300000'],
        ];
    }

    private function accountInternalId(): ?string
    {
        return SwitchAccount::query()
            ->where('id', (string) $this->route('account'))
            ->value('account_id');
    }
}
