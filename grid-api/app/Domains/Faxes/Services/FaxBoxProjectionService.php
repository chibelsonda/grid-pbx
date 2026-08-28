<?php

namespace App\Domains\Faxes\Services;

use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use UnexpectedValueException;

class FaxBoxProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactor) {}
    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchFaxBox
    {
        $resourceId = $this->string($snapshot['id'] ?? null); $name = $this->string($snapshot['name'] ?? null);
        if ($resourceId === null || $name === null) throw new UnexpectedValueException('Switch fax box response is missing required metadata.');
        $media = is_array($snapshot['media'] ?? null) ? $snapshot['media'] : []; $notifications = is_array($snapshot['notifications'] ?? null) ? $snapshot['notifications'] : [];
        $ownerReference = $this->string($snapshot['owner_id'] ?? null);
        $box = SwitchFaxBox::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'switch_resource_id' => $resourceId]);
        $box->fill([
            'owner_switch_resource_id' => $ownerReference, 'owner_extension_id' => $ownerReference === null ? null : $account->extensions()->where('switch_resource_id', $ownerReference)->value('extension_id'),
            'name' => $name, 'caller_id' => $this->string($snapshot['caller_id'] ?? null), 'caller_name' => $this->string($snapshot['caller_name'] ?? null),
            'fax_header' => $this->string($snapshot['fax_header'] ?? null), 'fax_identity' => $this->string($snapshot['fax_identity'] ?? null),
            'fax_timezone' => $this->string($snapshot['fax_timezone'] ?? null), 'retries' => max(0, min(4, (int) ($snapshot['retries'] ?? 1))),
            't38_enabled' => (bool) ($media['fax_option'] ?? false), 'smtp_email_address' => $this->string($snapshot['smtp_email_address'] ?? null),
            'custom_smtp_email_address' => $this->string($snapshot['custom_smtp_email_address'] ?? null), 'smtp_permission_list' => $this->strings($snapshot['smtp_permission_list'] ?? null),
            'inbound_notification_emails' => $this->emails($notifications, 'inbound'), 'outbound_notification_emails' => $this->emails($notifications, 'outbound'),
            'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => $box->exists ? $box->projection_version + 1 : 1,
            'switch_json' => $this->redactor->handle($snapshot),
        ]);
        $box->deleted_at = null; $box->save(); return $box->load('owner');
    }
    /** @param array<string, mixed> $notifications @return list<string> */
    private function emails(array $notifications, string $direction): array { $group = is_array($notifications[$direction] ?? null) ? $notifications[$direction] : []; $email = is_array($group['email'] ?? null) ? $group['email'] : []; $sendTo = $email['send_to'] ?? null; return is_string($sendTo) && $sendTo !== '' ? [$sendTo] : $this->strings($sendTo); }
    /** @return list<string> */ private function strings(mixed $value): array { return is_array($value) ? array_values(array_unique(array_filter($value, fn ($item) => is_string($item) && $item !== ''))) : []; }
    private function string(mixed $value): ?string { return is_string($value) && $value !== '' ? $value : null; }
}
