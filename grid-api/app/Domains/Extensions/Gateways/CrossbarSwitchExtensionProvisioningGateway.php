<?php

namespace App\Domains\Extensions\Gateways;

use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Dto\Callflows\CallflowCreateData;
use GridPbx\Switch\Dto\Callflows\CallflowSnapshot;
use GridPbx\Switch\Dto\Callflows\ManagedExtensionCallflowWriteData;
use GridPbx\Switch\Dto\Devices\DeviceWriteData;
use GridPbx\Switch\Dto\Users\UserWriteData;
use GridPbx\Switch\Dto\Voicemail\VoicemailBoxWriteData;
use GridPbx\Switch\Resources\AccountResource;
use GridPbx\Switch\Resources\AccountResourceClient;
use GridPbx\Switch\Resources\CallflowResourceClient;
use GridPbx\Switch\Resources\DeviceResourceClient;
use GridPbx\Switch\Resources\UserResourceClient;
use GridPbx\Switch\Resources\VoicemailBoxResourceClient;

class CrossbarSwitchExtensionProvisioningGateway implements SwitchExtensionProvisioningGateway
{
    public function __construct(
        private readonly UserResourceClient $users,
        private readonly VoicemailBoxResourceClient $voicemailBoxes,
        private readonly DeviceResourceClient $devices,
        private readonly CallflowResourceClient $callflows,
        private readonly AccountResourceClient $resources,
    ) {}

    public function createUser(SwitchAccount $account, array $data): array
    {
        return $this->users->create($account->switch_account_id, new UserWriteData(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            extension: $data['extension'],
            enabled: $data['is_enabled'],
            username: $data['username'] ?? null,
            email: $data['email'] ?? null,
            timezone: $data['timezone'] ?? null,
        ))->toArray();
    }

    public function deleteUser(SwitchAccount $account, string $resourceId): void
    {
        $this->users->delete($account->switch_account_id, $resourceId);
    }

    public function updateUser(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->users->update($account->switch_account_id, $resourceId, new UserWriteData(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            extension: $data['extension'],
            enabled: $data['is_enabled'],
            username: $data['username'] ?? null,
            email: $data['email'] ?? null,
            timezone: $data['timezone'] ?? null,
        ))->toArray();
    }

    public function createVoicemailBox(SwitchAccount $account, array $data): array
    {
        return $this->voicemailBoxes->create($account->switch_account_id, new VoicemailBoxWriteData(
            name: $data['name'],
            mailbox: $data['mailbox'],
            ownerId: $data['owner_id'],
            timezone: $data['timezone'] ?? null,
            notificationEmails: $data['notification_emails'],
            transcribe: $data['transcribe'],
            requirePin: $data['require_pin'],
            pin: $data['pin'] ?? null,
        ))->toArray();
    }

    public function deleteVoicemailBox(SwitchAccount $account, string $resourceId): void
    {
        $this->voicemailBoxes->delete($account->switch_account_id, $resourceId);
    }

    public function updateVoicemailBox(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->voicemailBoxes->update(
            $account->switch_account_id,
            $resourceId,
            new VoicemailBoxWriteData(
                name: $data['name'],
                mailbox: $data['mailbox'],
                ownerId: $data['owner_id'],
                timezone: $data['timezone'] ?? null,
                notificationEmails: $data['notification_emails'],
                transcribe: $data['transcribe'],
                requirePin: $data['require_pin'],
                pin: $data['pin'] ?? null,
            ),
        )->toArray();
    }

    public function createDevice(SwitchAccount $account, array $data): array
    {
        return $this->devices->create($account->switch_account_id, new DeviceWriteData(
            name: $data['name'],
            deviceType: $data['device_type'],
            enabled: true,
            ownerId: $data['owner_id'],
            make: $data['make'] ?? null,
            model: $data['model'] ?? null,
            macAddress: $data['mac_address'] ?? null,
            sipUsername: $data['sip_username'] ?? null,
            sipPassword: $data['sip_password'] ?? null,
        ))->toArray();
    }

    public function deleteDevice(SwitchAccount $account, string $resourceId): void
    {
        $this->devices->delete($account->switch_account_id, $resourceId);
    }

    public function createManagedCallflow(
        SwitchAccount $account,
        string $name,
        string $extension,
        string $userResourceId,
        ?string $voicemailBoxResourceId,
    ): array {
        return $this->callflows->create($account->switch_account_id, new CallflowCreateData(
            name: $name,
            destinationModule: 'user',
            destinationResourceId: $userResourceId,
            phoneNumbers: [$extension],
            fallbackModule: $voicemailBoxResourceId === null ? null : 'voicemail',
            fallbackResourceId: $voicemailBoxResourceId,
        ))->toArray();
    }

    public function deleteCallflow(SwitchAccount $account, string $resourceId): void
    {
        $this->callflows->delete($account->switch_account_id, $resourceId);
    }

    public function updateManagedCallflow(
        SwitchAccount $account,
        string $resourceId,
        string $userResourceId,
        string $previousExtension,
        string $extension,
        string $name,
        ?string $voicemailBoxResourceId,
    ): array {
        $current = $this->resources->find(
            $account->switch_account_id,
            AccountResource::Callflows,
            $resourceId,
        );

        if (! $current instanceof CallflowSnapshot) {
            throw new \UnexpectedValueException('Switch returned an unexpected managed callflow resource.');
        }

        return $this->callflows->updateManagedExtension(
            $account->switch_account_id,
            $resourceId,
            new ManagedExtensionCallflowWriteData(
                current: $current->toArray(),
                userResourceId: $userResourceId,
                previousExtension: $previousExtension,
                extension: $extension,
                name: $name,
                voicemailBoxResourceId: $voicemailBoxResourceId,
            ),
        )->toArray();
    }
}
