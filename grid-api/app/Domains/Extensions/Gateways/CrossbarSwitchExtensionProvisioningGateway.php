<?php

namespace App\Domains\Extensions\Gateways;

use App\Domains\Devices\Contracts\SwitchDeviceGateway;
use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Accounts\AccountResource;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Callflows\CallflowResourceClient;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowCreateData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use GridPbx\Switch\Domains\Callflows\Dto\ManagedExtensionCallflowWriteData;
use GridPbx\Switch\Domains\Users\Dto\CallerId\UserCallerIdData;
use GridPbx\Switch\Domains\Users\Dto\CallerId\UserCallerIdScopeData;
use GridPbx\Switch\Domains\Users\Dto\CallForwarding\UserCallForwardData;
use GridPbx\Switch\Domains\Users\Dto\CallRecording\UserCallRecordingData;
use GridPbx\Switch\Domains\Users\Dto\CallRecording\UserRecordingParametersData;
use GridPbx\Switch\Domains\Users\Dto\CallRecording\UserRecordingRulesData;
use GridPbx\Switch\Domains\Users\Dto\CallRecording\UserRecordingSourceData;
use GridPbx\Switch\Domains\Users\Dto\CallRestrictions\UserCallRestrictionsData;
use GridPbx\Switch\Domains\Users\Dto\Credentials\UserCredentialsData;
use GridPbx\Switch\Domains\Users\Dto\Hotdesk\UserHotdeskData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserMediaData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserMusicOnHoldData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserPronouncedNameData;
use GridPbx\Switch\Domains\Users\Dto\Media\UserRingtonesData;
use GridPbx\Switch\Domains\Users\Dto\Metaflows\UserMetaflowsData;
use GridPbx\Switch\Domains\Users\Dto\Profile\UserProfileAddressData;
use GridPbx\Switch\Domains\Users\Dto\Profile\UserProfileData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserDialPlanData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserDialPlanRuleData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserFormatterRuleData;
use GridPbx\Switch\Domains\Users\Dto\Routing\UserFormattersData;
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
        private readonly SwitchDeviceGateway $devices,
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
            metaflows: isset($data['metaflows']) && is_array($data['metaflows'])
                ? new UserMetaflowsData(
                    bindingDigit: $data['metaflows']['binding_digit'] ?? null,
                    digitTimeout: $data['metaflows']['digit_timeout'] ?? null,
                    listenOn: $data['metaflows']['listen_on'] ?? null,
                    preservedOptions: is_array($data['metaflows']['preserved_options'] ?? null)
                        ? $data['metaflows']['preserved_options']
                        : [],
                )
                : null,
            callerId: $this->userCallerIdData($data['caller_id'] ?? null),
            callForward: $this->userCallForwardData($data['call_forward'] ?? null),
            callRestrictions: $this->userCallRestrictionsData($data['call_restriction'] ?? null),
            callRecording: $this->userCallRecordingData($data['call_recording'] ?? null),
            media: $this->userMediaData($data['media'] ?? null),
            musicOnHold: $this->userMusicOnHoldData($data['music_on_hold'] ?? null),
            ringtones: $this->userRingtonesData($data['ringtones'] ?? null),
            dialPlan: $this->userDialPlanData($data['dial_plan'] ?? null),
            formatters: $this->userFormattersData($data['formatters'] ?? null),
            profile: $this->userProfileData($data['profile'] ?? null),
            pronouncedName: $this->userPronouncedNameData($data['pronounced_name'] ?? null),
            preservedOptions: is_array($data['advanced_preserved_options'] ?? null)
                ? $data['advanced_preserved_options']
                : [],
        );
    }

    private function userDialPlanData(mixed $value): ?UserDialPlanData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserDialPlanData(
            system: (array) ($value['system'] ?? []),
            rules: array_map(
                static fn (array $rule): UserDialPlanRuleData => new UserDialPlanRuleData(
                    pattern: $rule['pattern'],
                    description: $rule['description'] ?? null,
                    prefix: $rule['prefix'] ?? null,
                    suffix: $rule['suffix'] ?? null,
                    preservedOptions: $rule['preserved_options'] ?? [],
                ),
                (array) ($value['rules'] ?? []),
            ),
        );
    }

    private function userFormattersData(mixed $value): ?UserFormattersData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserFormattersData(array_map(
            static fn (array $formatter): UserFormatterRuleData => new UserFormatterRuleData(
                field: $formatter['field'],
                direction: $formatter['direction'] ?? null,
                matchInviteFormat: $formatter['match_invite_format'] ?? null,
                prefix: $formatter['prefix'] ?? null,
                regex: $formatter['regex'] ?? null,
                strip: $formatter['strip'] ?? null,
                suffix: $formatter['suffix'] ?? null,
                value: $formatter['value'] ?? null,
                preservedOptions: $formatter['preserved_options'] ?? [],
            ),
            $value,
        ));
    }

    private function userProfileData(mixed $value): ?UserProfileData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserProfileData(
            addresses: array_map(
                static fn (array $address): UserProfileAddressData => new UserProfileAddressData(
                    address: $address['address'],
                    types: $address['types'] ?? [],
                ),
                (array) ($value['addresses'] ?? []),
            ),
            assistant: $this->nullableString($value['assistant'] ?? null),
            birthday: $this->nullableString($value['birthday'] ?? null),
            nicknames: (array) ($value['nicknames'] ?? []),
            note: $this->nullableString($value['note'] ?? null),
            role: $this->nullableString($value['role'] ?? null),
            sortString: $this->nullableString($value['sort_string'] ?? null),
            title: $this->nullableString($value['title'] ?? null),
            preservedOptions: is_array($value['preserved_options'] ?? null)
                ? $value['preserved_options']
                : [],
        );
    }

    private function userPronouncedNameData(mixed $value): ?UserPronouncedNameData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserPronouncedNameData(
            mediaId: $this->nullableString($value['media_id'] ?? null),
            preservedOptions: is_array($value['preserved_options'] ?? null)
                ? $value['preserved_options']
                : [],
        );
    }

    private function userMediaData(mixed $value): ?UserMediaData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserMediaData(
            audioCodecs: (array) data_get($value, 'audio.codecs', []),
            videoCodecs: (array) data_get($value, 'video.codecs', []),
            bypassMedia: $value['bypass_media'] ?? false,
            enforceEncryption: (bool) data_get($value, 'encryption.enforce_security', false),
            encryptionMethods: (array) data_get($value, 'encryption.methods', []),
            faxOption: (bool) ($value['fax_option'] ?? false),
            ignoreEarlyMedia: (bool) ($value['ignore_early_media'] ?? false),
            progressTimeout: isset($value['progress_timeout']) ? (int) $value['progress_timeout'] : null,
            preservedOptions: is_array($value['preserved_options'] ?? null)
                ? $value['preserved_options']
                : [],
        );
    }

    private function userMusicOnHoldData(mixed $value): ?UserMusicOnHoldData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserMusicOnHoldData(
            mediaId: $this->nullableString($value['media_id'] ?? null),
            preservedOptions: is_array($value['preserved_options'] ?? null)
                ? $value['preserved_options']
                : [],
        );
    }

    private function userRingtonesData(mixed $value): ?UserRingtonesData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserRingtonesData(
            internal: $this->nullableString($value['internal'] ?? null),
            external: $this->nullableString($value['external'] ?? null),
            preservedOptions: is_array($value['preserved_options'] ?? null)
                ? $value['preserved_options']
                : [],
        );
    }

    private function userCallerIdData(mixed $value): ?UserCallerIdData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserCallerIdData(
            internal: $this->userCallerIdScopeData($value['internal'] ?? null),
            external: $this->userCallerIdScopeData($value['external'] ?? null),
            emergency: $this->userCallerIdScopeData($value['emergency'] ?? null),
            preservedOptions: is_array($value['preserved_options'] ?? null)
                ? $value['preserved_options']
                : [],
        );
    }

    private function userCallerIdScopeData(mixed $value): UserCallerIdScopeData
    {
        $value = is_array($value) ? $value : [];

        return new UserCallerIdScopeData(
            name: $this->nullableString($value['name'] ?? null),
            number: $this->nullableString($value['number'] ?? null),
            preservedOptions: is_array($value['preserved_options'] ?? null)
                ? $value['preserved_options']
                : [],
        );
    }

    private function userCallForwardData(mixed $value): ?UserCallForwardData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserCallForwardData(
            enabled: (bool) ($value['enabled'] ?? false),
            number: $this->nullableString($value['number'] ?? null),
            directCallsOnly: (bool) ($value['direct_calls_only'] ?? false),
            failover: (bool) ($value['failover'] ?? false),
            ignoreEarlyMedia: (bool) ($value['ignore_early_media'] ?? true),
            keepCallerId: (bool) ($value['keep_caller_id'] ?? true),
            requireKeypress: (bool) ($value['require_keypress'] ?? true),
            substitute: (bool) ($value['substitute'] ?? true),
            preservedOptions: is_array($value['preserved_options'] ?? null)
                ? $value['preserved_options']
                : [],
        );
    }

    private function userCallRestrictionsData(mixed $value): ?UserCallRestrictionsData
    {
        if (! is_array($value)) {
            return null;
        }

        $preserved = is_array($value['preserved_options'] ?? null)
            ? $value['preserved_options']
            : [];
        unset($value['preserved_options']);

        return new UserCallRestrictionsData(
            actions: array_map(
                static fn (array $restriction): string => (string) $restriction['action'],
                $value,
            ),
            preservedOptions: $preserved,
        );
    }

    private function userCallRecordingData(mixed $value): ?UserCallRecordingData
    {
        if (! is_array($value)) {
            return null;
        }

        return new UserCallRecordingData(
            account: $this->userRecordingRulesData($value['account'] ?? null),
            endpoint: $this->userRecordingRulesData($value['endpoint'] ?? null),
        );
    }

    private function userRecordingRulesData(mixed $value): UserRecordingRulesData
    {
        $value = is_array($value) ? $value : [];

        return new UserRecordingRulesData(
            any: $this->userRecordingSourceData($value['any'] ?? null),
            inbound: $this->userRecordingSourceData($value['inbound'] ?? null),
            outbound: $this->userRecordingSourceData($value['outbound'] ?? null),
        );
    }

    private function userRecordingSourceData(mixed $value): UserRecordingSourceData
    {
        $value = is_array($value) ? $value : [];

        return new UserRecordingSourceData(
            any: $this->userRecordingParametersData($value['any'] ?? null),
            onnet: $this->userRecordingParametersData($value['onnet'] ?? null),
            offnet: $this->userRecordingParametersData($value['offnet'] ?? null),
        );
    }

    private function userRecordingParametersData(mixed $value): UserRecordingParametersData
    {
        $value = is_array($value) ? $value : [];

        return new UserRecordingParametersData(
            enabled: (bool) ($value['enabled'] ?? false),
            format: is_string($value['format'] ?? null) ? $value['format'] : 'mp3',
            minimumSeconds: is_int($value['record_min_sec'] ?? null) ? $value['record_min_sec'] : null,
            recordOnAnswer: (bool) ($value['record_on_answer'] ?? false),
            recordOnBridge: (bool) ($value['record_on_bridge'] ?? false),
            sampleRate: is_int($value['record_sample_rate'] ?? null) ? $value['record_sample_rate'] : null,
            timeLimit: is_int($value['time_limit'] ?? null) ? $value['time_limit'] : null,
            preservedOptions: is_array($value['preserved_options'] ?? null)
                ? $value['preserved_options']
                : [],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
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
        return $this->devices->create($account, $data);
    }

    public function deleteDevice(SwitchAccount $account, string $resourceId): void
    {
        $this->devices->delete($account, $resourceId);
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
