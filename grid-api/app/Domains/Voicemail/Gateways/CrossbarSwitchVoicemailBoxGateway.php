<?php

namespace App\Domains\Voicemail\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Voicemail\Contracts\SwitchVoicemailBoxGateway;
use GridPbx\Switch\Dto\Voicemail\VoicemailBoxWriteData;
use GridPbx\Switch\Resources\VoicemailBoxResourceClient;

class CrossbarSwitchVoicemailBoxGateway implements SwitchVoicemailBoxGateway
{
    public function __construct(private readonly VoicemailBoxResourceClient $voicemailBoxes) {}

    public function create(SwitchAccount $account, array $voicemailBox): array
    {
        return $this->voicemailBoxes
            ->create($account->switch_account_id, $this->writeData($voicemailBox))
            ->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $voicemailBox): array
    {
        return $this->voicemailBoxes
            ->update($account->switch_account_id, $resourceId, $this->writeData($voicemailBox))
            ->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->voicemailBoxes->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $voicemailBox */
    private function writeData(array $voicemailBox): VoicemailBoxWriteData
    {
        return new VoicemailBoxWriteData(
            name: $voicemailBox['name'],
            mailbox: $voicemailBox['mailbox'],
            ownerId: $voicemailBox['owner_switch_resource_id'],
            timezone: $voicemailBox['timezone'],
            notificationEmails: $voicemailBox['notification_emails'],
            transcribe: $voicemailBox['transcribe'],
            requirePin: $voicemailBox['require_pin'],
            pin: $voicemailBox['pin'],
        );
    }
}
