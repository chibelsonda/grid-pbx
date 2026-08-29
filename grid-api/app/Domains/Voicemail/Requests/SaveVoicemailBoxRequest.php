<?php

namespace App\Domains\Voicemail\Requests;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'pin' => [
                Rule::requiredIf(
                    $this->boolean('require_pin') && ! $this->projectedPinConfigured(),
                ),
                'nullable',
                'string',
                'regex:/^\d{4,6}$/',
            ],
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
            'notify_callback' => ['present', 'nullable', 'array:disabled,number,attempts,interval_s,timeout_s,schedule'],
            'notify_callback.disabled' => ['required_with:notify_callback', 'boolean'],
            'notify_callback.number' => ['nullable', 'string', 'max:64'],
            'notify_callback.attempts' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notify_callback.interval_s' => ['nullable', 'integer', 'min:0', 'max:604800'],
            'notify_callback.timeout_s' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'notify_callback.schedule' => ['sometimes', 'array', 'max:100'],
            'notify_callback.schedule.*' => ['integer', 'min:0', 'max:604800'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('save_after_notify') && $this->boolean('delete_after_notify')) {
                    $validator->errors()->add(
                        'delete_after_notify',
                        'Delete after notification cannot be enabled while save after notification is enabled.',
                    );
                }

                $callback = $this->input('notify_callback');

                if (is_array($callback)
                    && ($callback['disabled'] ?? null) === false
                    && trim((string) ($callback['number'] ?? '')) === '') {
                    $validator->errors()->add(
                        'notify_callback.number',
                        'Enter a callback number when callback notifications are enabled.',
                    );
                }
            },
        ];
    }

    private function accountInternalId(): ?string
    {
        return SwitchAccount::query()
            ->where('id', (string) $this->route('account'))
            ->value('account_id');
    }

    private function projectedPinConfigured(): bool
    {
        $voicemailBox = $this->route('voicemailBox');

        if (! is_string($voicemailBox) || $voicemailBox === '') {
            return false;
        }

        return SwitchVoicemailBox::query()
            ->where('switch_account_id', $this->accountInternalId())
            ->where('id', $voicemailBox)
            ->first()
            ?->pinConfigured() ?? false;
    }
}
