<?php

namespace App\Domains\Organizations\Gateways;

use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Accounts\Dto\AccountCallerIdWriteData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountEnabledWriteData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountSettingsWriteData;

class CrossbarSwitchAccountGateway implements SwitchAccountGateway
{
    public function __construct(private readonly AccountResourceClient $accounts) {}

    public function find(SwitchAccount $account): array
    {
        return $this->accounts->account($account->switch_account_id)->toArray();
    }

    public function updateSettings(SwitchAccount $account, array $data): array
    {
        return $this->accounts->updateSettings(
            $account->switch_account_id,
            new AccountSettingsWriteData(
                name: (string) $data['name'],
                organizationName: $this->nullableString($data['organization_name'] ?? null),
                timezone: $this->nullableString($data['timezone'] ?? null),
                language: $this->nullableString($data['language'] ?? null),
                callWaitingEnabled: (bool) $data['call_waiting_enabled'],
                doNotDisturbEnabled: (bool) $data['do_not_disturb_enabled'],
                outboundPrivacy: (string) $data['outbound_privacy'],
                showRate: (bool) $data['show_rate'],
                internalRingtone: $this->nullableString($data['ringtone_internal'] ?? null),
                externalRingtone: $this->nullableString($data['ringtone_external'] ?? null),
                callerId: new AccountCallerIdWriteData(
                    internalName: $this->nullableString($data['caller_id']['internal']['name'] ?? null),
                    internalNumber: $this->nullableString($data['caller_id']['internal']['number'] ?? null),
                    externalName: $this->nullableString($data['caller_id']['external']['name'] ?? null),
                    externalNumber: $this->nullableString($data['caller_id']['external']['number'] ?? null),
                    emergencyName: $this->nullableString($data['caller_id']['emergency']['name'] ?? null),
                    emergencyNumber: $this->nullableString($data['caller_id']['emergency']['number'] ?? null),
                ),
            ),
        )->toArray();
    }

    public function updateEnabled(SwitchAccount $account, bool $enabled): array
    {
        return $this->accounts->updateEnabled(
            $account->switch_account_id,
            new AccountEnabledWriteData($enabled),
        )->toArray();
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
