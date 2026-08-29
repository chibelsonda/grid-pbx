<?php

namespace App\Domains\Extensions\Gateways;

use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Accounts\AccountResource;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Callflows\CallflowResourceClient;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowCreateData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use GridPbx\Switch\Domains\Callflows\Dto\ManagedExtensionCallflowWriteData;
use GridPbx\Switch\Domains\Devices\DeviceResourceClient;
use GridPbx\Switch\Domains\Devices\Dto\DeviceWriteData;
use GridPbx\Switch\Domains\Users\Dto\Credentials\UserCredentialsData;
use GridPbx\Switch\Domains\Users\Dto\Hotdesk\UserHotdeskData;
use GridPbx\Switch\Domains\Users\Dto\UserAdvancedData;
use GridPbx\Switch\Domains\Users\Dto\UserWriteData;
use GridPbx\Switch\Domains\Users\UserResourceClient;
use GridPbx\Switch\Domains\Voicemail\Dto\VoicemailBoxWriteData;
use GridPbx\Switch\Domains\Voicemail\VoicemailBoxResourceClient;

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
            email: $data['email'] ?? null,
            timezone: $data['timezone'] ?? null,
            advanced: $this->userAdvancedData($data),
            hotdesk: $this->userHotdeskData($data),
            credentials: $this->userCredentialsData($data),
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
            email: $data['email'] ?? null,
            timezone: $data['timezone'] ?? null,
            advanced: $this->userAdvancedData($data),
            hotdesk: $this->userHotdeskData($data, true),
            credentials: $this->userCredentialsData($data),
        ))->toArray();
    }

    /** @param array<string, mixed> $data */
    private function userAdvancedData(array $data): UserAdvancedData
    {
        return new UserAdvancedData(
            language: $data['language'] ?? null,
            presenceId: $data['presence_id'] ?? null,
            callWaiting: $data['call_waiting']['enabled'] ?? null,
            doNotDisturb: $data['do_not_disturb']['enabled'] ?? null,
            excludeFromContactList: $data['contact_list']['exclude'] ?? null,
            outboundPrivacy: $data['caller_id_options']['outbound_privacy'] ?? null,
        );
    }

    /** @param array<string, mixed> $data */
    private function userCredentialsData(array $data): UserCredentialsData
    {
        return new UserCredentialsData(
            username: isset($data['username']) && is_string($data['username']) && $data['username'] !== ''
                ? $data['username']
                : null,
            password: isset($data['password']) && is_string($data['password']) && $data['password'] !== ''
                ? $data['password']
                : null,
            requirePasswordUpdate: (bool) ($data['require_password_update'] ?? false),
        );
    }

    /** @param array<string, mixed> $data */
    private function userHotdeskData(array $data, bool $updating = false): ?UserHotdeskData
    {
        $hotdesk = $data['hotdesk'] ?? null;

        if (! is_array($hotdesk)) {
            return null;
        }

        $pin = isset($hotdesk['pin']) && is_string($hotdesk['pin']) && $hotdesk['pin'] !== ''
            ? $hotdesk['pin']
            : null;
        $requirePin = (bool) ($hotdesk['require_pin'] ?? false);

        return new UserHotdeskData(
            enabled: (bool) ($hotdesk['enabled'] ?? false),
            id: isset($hotdesk['id']) && is_string($hotdesk['id']) && $hotdesk['id'] !== ''
                ? $hotdesk['id']
                : null,
            keepLoggedInElsewhere: (bool) ($hotdesk['keep_logged_in_elsewhere'] ?? false),
            requirePin: $requirePin,
            pin: $pin,
            preservePin: $updating
                && $requirePin
                && $pin === null
                && ! (bool) ($hotdesk['clear_pin'] ?? false),
        );
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
