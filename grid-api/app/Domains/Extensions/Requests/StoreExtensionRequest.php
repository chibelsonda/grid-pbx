<?php

namespace App\Domains\Extensions\Requests;

use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'voicemail' => ['required', 'array:enabled,notification_emails,transcribe,require_pin,pin'],
            'voicemail.enabled' => ['required', 'boolean'],
            'voicemail.notification_emails' => ['required', 'array', 'max:10'],
            'voicemail.notification_emails.*' => ['email:rfc', 'max:254', 'distinct'],
            'voicemail.transcribe' => ['required', 'boolean'],
            'voicemail.require_pin' => ['required', 'boolean'],
            'voicemail.pin' => ['nullable', 'required_if:voicemail.require_pin,true', 'string', 'regex:/^[0-9]{4,6}$/'],
            'device' => ['required', 'array:enabled,name,device_type,make,model,mac_address,sip_username,sip_password'],
            'device.enabled' => ['required', 'boolean'],
            'device.name' => ['nullable', 'required_if:device.enabled,true', 'string', 'max:255'],
            'device.device_type' => [
                'nullable',
                'required_if:device.enabled,true',
                'string',
                Rule::in(['sip_device', 'cellphone', 'smartphone', 'softphone', 'landline', 'fax', 'ata', 'sip_uri']),
            ],
            'device.make' => ['nullable', 'string', 'max:255'],
            'device.model' => ['nullable', 'string', 'max:255'],
            'device.mac_address' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/',
            ],
            'device.sip_username' => ['nullable', 'string', 'min:2', 'max:32'],
            'device.sip_password' => ['nullable', 'string', 'min:12', 'max:32'],
        ];
    }

    private function accountInternalId(): ?string
    {
        return SwitchAccount::query()
            ->where('id', (string) $this->route('account'))
            ->value('account_id');
    }
}
