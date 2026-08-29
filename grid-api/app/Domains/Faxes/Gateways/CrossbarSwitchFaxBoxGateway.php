<?php

namespace App\Domains\Faxes\Gateways;

use App\Domains\Faxes\Contracts\SwitchFaxBoxGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Generator;
use GridPbx\Switch\Domains\Faxes\Dto\FaxBoxWriteData;
use GridPbx\Switch\Domains\Faxes\FaxBoxResourceClient;

class CrossbarSwitchFaxBoxGateway implements SwitchFaxBoxGateway
{
    public function __construct(private readonly FaxBoxResourceClient $faxBoxes) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->faxBoxes->allDetails($account->switch_account_id) as $box) {
            yield $box->toArray();
        }
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->faxBoxes->create($account->switch_account_id, $this->writeData($data))->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->faxBoxes->update($account->switch_account_id, $resourceId, $this->writeData($data))->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->faxBoxes->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $data */
    private function writeData(array $data): FaxBoxWriteData
    {
        return new FaxBoxWriteData(
            name: (string) $data['name'], ownerId: $data['switch_owner_reference'] ?? null,
            callerId: $data['caller_id'] ?? null, callerName: $data['caller_name'] ?? null,
            faxHeader: $data['fax_header'] ?? null, faxIdentity: $data['fax_identity'] ?? null,
            timezone: $data['fax_timezone'] ?? null, retries: (int) $data['retries'],
            t38Enabled: (bool) $data['t38_enabled'], customSmtpEmailAddress: $data['custom_smtp_email_address'] ?? null,
            smtpPermissionList: $data['smtp_permission_list'], inboundNotificationEmails: $data['inbound_notification_emails'],
            outboundNotificationEmails: $data['outbound_notification_emails'],
            inboundNotificationExtras: $data['switch_inbound_notification_extras'] ?? [],
            outboundNotificationExtras: $data['switch_outbound_notification_extras'] ?? [],
            flags: $data['switch_flags'] ?? [],
        );
    }
}
