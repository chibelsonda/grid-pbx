<?php

namespace App\Domains\Faxes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveFaxBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'], 'owner_id' => ['nullable', 'uuid'],
            'caller_id' => ['nullable', 'string', 'max:64'], 'caller_name' => ['nullable', 'string', 'max:128'],
            'fax_header' => ['nullable', 'string', 'max:128'], 'fax_identity' => ['nullable', 'string', 'max:64'],
            'fax_timezone' => ['nullable', 'timezone:all'], 'retries' => ['required', 'integer', 'min:0', 'max:4'], 't38_enabled' => ['required', 'boolean'],
            'custom_smtp_email_address' => ['nullable', 'email:rfc', 'max:255'], 'smtp_permission_list' => ['required', 'array', 'max:50'], 'smtp_permission_list.*' => ['string', 'max:255', 'distinct'],
            'inbound_notification_emails' => ['required', 'array', 'max:20'], 'inbound_notification_emails.*' => ['email:rfc', 'max:255', 'distinct'],
            'outbound_notification_emails' => ['required', 'array', 'max:20'], 'outbound_notification_emails.*' => ['email:rfc', 'max:255', 'distinct'],
            'attempts' => ['prohibited'], 'flags' => ['prohibited'], 'notifications' => ['prohibited'],
            'switch_flags' => ['prohibited'], 'switch_inbound_notification_extras' => ['prohibited'], 'switch_outbound_notification_extras' => ['prohibited'],
        ];
    }
}
