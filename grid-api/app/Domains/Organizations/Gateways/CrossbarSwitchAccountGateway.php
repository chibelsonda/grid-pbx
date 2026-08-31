<?php

namespace App\Domains\Organizations\Gateways;

use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Accounts\Dto\AccountCallerIdWriteData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountCallRecordingData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountCallRestrictionsData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountDialPlanData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountDialPlanRuleData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountEnabledWriteData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountFormatterRuleData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountFormattersData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountHierarchySnapshot;
use GridPbx\Switch\Domains\Accounts\Dto\AccountMetaflowsData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountPreflowData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountRecordingParametersData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountRecordingRulesData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountRecordingSourceData;
use GridPbx\Switch\Domains\Accounts\Dto\AccountSettingsWriteData;
use GridPbx\Switch\Domains\PhoneNumbers\Dto\NumberClassifierSnapshot;
use GridPbx\Switch\Domains\PhoneNumbers\PhoneNumberResourceClient;

class CrossbarSwitchAccountGateway implements SwitchAccountGateway
{
    public function __construct(
        private readonly AccountResourceClient $accounts,
        private readonly PhoneNumberResourceClient $phoneNumbers,
    ) {}

    public function restrictionClassifiers(SwitchAccount $account): array
    {
        return array_map(
            static fn (NumberClassifierSnapshot $classifier): array => [
                'key' => $classifier->key,
                'label' => $classifier->friendlyName,
                'emergency' => $classifier->emergency,
            ],
            $this->phoneNumbers->classifiers($account->switch_account_id),
        );
    }

    public function find(SwitchAccount $account): array
    {
        return $this->accounts->account($account->switch_account_id)->toArray();
    }

    public function descendants(SwitchAccount $account): array
    {
        return array_map(
            static fn (AccountHierarchySnapshot $descendant): array => [
                'id' => $descendant->id,
                'name' => $descendant->name,
                'realm' => $descendant->realm,
                'tree' => $descendant->tree,
                'parent_id' => $descendant->parentId,
                'descendants_count' => $descendant->descendantsCount,
            ],
            $this->accounts->descendants($account->switch_account_id),
        );
    }

    public function findBySwitchAccountId(string $switchAccountId): array
    {
        return $this->accounts->account($switchAccountId)->toArray();
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
                outboundPrivacy: $this->nullableString($data['outbound_privacy'] ?? null),
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
                callRestrictions: isset($data['call_restriction']) && is_array($data['call_restriction'])
                    ? new AccountCallRestrictionsData(array_map(
                        static fn (array $restriction): string => (string) $restriction['action'],
                        $data['call_restriction'],
                    ))
                    : null,
                callRecording: $this->callRecordingData($data['call_recording'] ?? null),
                dialPlan: $this->dialPlanData($data['dial_plan'] ?? null),
                formatters: $this->formattersData($data['formatters'] ?? null),
                preflow: $this->preflowData($data['preflow'] ?? null),
                metaflows: $this->metaflowsData($data['metaflows'] ?? null),
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

    private function callRecordingData(mixed $value): ?AccountCallRecordingData
    {
        if (! is_array($value)) {
            return null;
        }

        return new AccountCallRecordingData(
            account: $this->recordingRulesData($value['account'] ?? null),
            endpoint: $this->recordingRulesData($value['endpoint'] ?? null),
        );
    }

    private function dialPlanData(mixed $value): ?AccountDialPlanData
    {
        if (! is_array($value)) {
            return null;
        }

        return new AccountDialPlanData(
            system: $value['system'] ?? [],
            rules: array_map(
                static fn (array $rule): AccountDialPlanRuleData => new AccountDialPlanRuleData(
                    pattern: $rule['pattern'],
                    description: $rule['description'] ?? null,
                    prefix: $rule['prefix'] ?? null,
                    suffix: $rule['suffix'] ?? null,
                    preservedOptions: $rule['preserved_options'] ?? [],
                ),
                $value['rules'] ?? [],
            ),
        );
    }

    private function formattersData(mixed $value): ?AccountFormattersData
    {
        if (! is_array($value)) {
            return null;
        }

        return new AccountFormattersData(array_map(
            static fn (array $formatter): AccountFormatterRuleData => new AccountFormatterRuleData(
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

    private function preflowData(mixed $value): ?AccountPreflowData
    {
        return is_array($value)
            ? new AccountPreflowData($this->nullableString($value['always'] ?? null))
            : null;
    }

    private function metaflowsData(mixed $value): ?AccountMetaflowsData
    {
        return is_array($value)
            ? new AccountMetaflowsData(
                bindingDigit: $this->nullableString($value['binding_digit'] ?? null),
                digitTimeout: is_int($value['digit_timeout'] ?? null) ? $value['digit_timeout'] : null,
                listenOn: $this->nullableString($value['listen_on'] ?? null),
                preservedOptions: is_array($value['preserved_options'] ?? null)
                    ? $value['preserved_options']
                    : [],
            )
            : null;
    }

    private function recordingRulesData(mixed $value): ?AccountRecordingRulesData
    {
        if (! is_array($value)) {
            return null;
        }

        return new AccountRecordingRulesData(
            any: $this->recordingSourceData($value['any'] ?? null),
            inbound: $this->recordingSourceData($value['inbound'] ?? null),
            outbound: $this->recordingSourceData($value['outbound'] ?? null),
        );
    }

    private function recordingSourceData(mixed $value): ?AccountRecordingSourceData
    {
        if (! is_array($value)) {
            return null;
        }

        return new AccountRecordingSourceData(
            any: $this->recordingParametersData($value['any'] ?? null),
            onnet: $this->recordingParametersData($value['onnet'] ?? null),
            offnet: $this->recordingParametersData($value['offnet'] ?? null),
        );
    }

    private function recordingParametersData(mixed $value): ?AccountRecordingParametersData
    {
        if (! is_array($value)) {
            return null;
        }

        return new AccountRecordingParametersData(
            enabled: $value['enabled'] ?? null,
            format: $this->nullableString($value['format'] ?? null),
            minimumSeconds: $value['record_min_sec'] ?? null,
            recordOnAnswer: $value['record_on_answer'] ?? null,
            recordOnBridge: $value['record_on_bridge'] ?? null,
            sampleRate: $value['record_sample_rate'] ?? null,
            timeLimit: $value['time_limit'] ?? null,
            preservedUrl: $this->nullableString($value['url'] ?? null),
        );
    }
}
