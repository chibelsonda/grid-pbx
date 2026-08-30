<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Switch\MetaflowPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountSettingsService
{
    public function __construct(
        private readonly SwitchAccountGateway $gateway,
        private readonly AccountProjectionService $projection,
        private readonly AccountHierarchyProjectionService $hierarchyProjection,
        private readonly AuditService $audit,
        private readonly MetaflowPolicy $metaflowPolicy,
    ) {}

    public function refresh(SwitchAccount $account, User $actor, ?string $ipAddress = null): SwitchAccount
    {
        $snapshot = $this->gateway->find($account);
        $descendants = ($snapshot['is_reseller'] ?? false) === true
            || (is_numeric($snapshot['descendants_count'] ?? null) && (int) $snapshot['descendants_count'] > 0)
                ? $this->gateway->descendants($account)
                : [];

        return DB::transaction(function () use ($account, $actor, $descendants, $ipAddress, $snapshot): SwitchAccount {
            $projected = $this->projection->project($account, $snapshot);
            $this->hierarchyProjection->project($projected, $snapshot, $descendants);
            $this->audit->record(
                $actor,
                $projected,
                'account.settings_refreshed',
                'succeeded',
                $projected->switch_account_id,
                [
                    'descendants_count' => $projected->descendants_count,
                    'projection_version' => $projected->projection_version,
                ],
                $ipAddress,
                'account',
            );

            return $projected;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(
        SwitchAccount $account,
        User $actor,
        array $data,
        ?string $ipAddress = null,
    ): SwitchAccount {
        try {
            $data = $this->resolveCallerIdNumbers($account, $data);
            $data = $this->resolvePreflow($account, $data);
            $data = $this->preserveAdvancedRuleMetadata($account, $data);
            $snapshot = $this->gateway->updateSettings(
                $account,
                $this->preserveRecordingStorageUrls($account, $data),
            );

            return DB::transaction(function () use ($account, $actor, $ipAddress, $snapshot): SwitchAccount {
                $projected = $this->projection->project($account, $snapshot);
                $this->audit->record(
                    $actor,
                    $projected,
                    'account.settings_updated',
                    'succeeded',
                    $projected->switch_account_id,
                    ['projection_version' => $projected->projection_version],
                    $ipAddress,
                    'account',
                );

                return $projected;
            });
        } catch (Throwable $exception) {
            $this->audit->record(
                $actor,
                $account,
                'account.settings_updated',
                'failed',
                $account->switch_account_id,
                ['error' => $exception->getMessage()],
                $ipAddress,
                'account',
            );

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveCallerIdNumbers(SwitchAccount $account, array $data): array
    {
        foreach (['external' => false, 'emergency' => true] as $scope => $requiresE911) {
            $preserveNumber = data_get($data, "caller_id.{$scope}.preserve_number") === true;
            $publicId = data_get($data, "caller_id.{$scope}.phone_number_id");
            if ($preserveNumber) {
                $data['caller_id'][$scope]['number'] = data_get($account->switch_json, "caller_id.{$scope}.number");
                unset(
                    $data['caller_id'][$scope]['phone_number_id'],
                    $data['caller_id'][$scope]['preserve_number'],
                );

                continue;
            }

            $phoneNumber = is_string($publicId) && $publicId !== ''
                ? $account->phoneNumbers()->where('id', $publicId)->first()
                : null;

            if (is_string($publicId) && $publicId !== '' && $phoneNumber === null) {
                throw ValidationException::withMessages([
                    "caller_id.{$scope}.phone_number_id" => 'Select a phone number assigned to this account.',
                ]);
            }

            if ($requiresE911 && $phoneNumber !== null && ! $phoneNumber->isE911Enabled()) {
                throw ValidationException::withMessages([
                    'caller_id.emergency.phone_number_id' => 'Select a phone number with E911 enabled.',
                ]);
            }

            $data['caller_id'][$scope]['number'] = $phoneNumber?->number;
            unset(
                $data['caller_id'][$scope]['phone_number_id'],
                $data['caller_id'][$scope]['preserve_number'],
            );
        }

        return $data;
    }

    /**
     * Preserve Switch-managed storage targets without accepting or returning URLs through the UI.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preserveRecordingStorageUrls(SwitchAccount $account, array $data): array
    {
        if (! isset($data['call_recording']) || ! is_array($data['call_recording'])) {
            return $data;
        }

        foreach (['account', 'endpoint'] as $target) {
            foreach (['any', 'inbound', 'outbound'] as $direction) {
                foreach (['any', 'onnet', 'offnet'] as $network) {
                    $path = "call_recording.{$target}.{$direction}.{$network}.url";
                    $url = data_get($account->switch_json, $path);

                    if (is_string($url) && $url !== '') {
                        data_set($data, $path, $url);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Resolve a public Callflow UUID while retaining an unresolved current Switch reference only
     * when the administrator explicitly chooses preservation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolvePreflow(SwitchAccount $account, array $data): array
    {
        if (! isset($data['preflow']) || ! is_array($data['preflow'])) {
            return $data;
        }

        if (($data['preflow']['preserve_callflow'] ?? false) === true) {
            $data['preflow'] = [
                'always' => data_get($account->switch_json, 'preflow.always'),
            ];

            return $data;
        }

        $publicId = $data['preflow']['callflow_id'] ?? null;
        $callflow = is_string($publicId) && $publicId !== ''
            ? $account->callflows()->where('id', $publicId)->first()
            : null;

        if (is_string($publicId)
            && $publicId !== ''
            && ($callflow === null || ! is_string($callflow->switch_resource_id) || $callflow->switch_resource_id === '')) {
            throw ValidationException::withMessages([
                'preflow.callflow_id' => 'Select a callflow projected for this account.',
            ]);
        }

        $data['preflow'] = ['always' => $callflow?->switch_resource_id];

        return $data;
    }

    /**
     * Merge server-owned future schema properties without exposing them as form fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preserveAdvancedRuleMetadata(SwitchAccount $account, array $data): array
    {
        $snapshot = is_array($account->switch_json) ? $account->switch_json : [];
        $currentDialPlan = is_array($snapshot['dial_plan'] ?? null) ? $snapshot['dial_plan'] : [];

        foreach ($data['dial_plan']['rules'] ?? [] as $index => $rule) {
            if (! is_array($rule) || ! is_string($rule['pattern'] ?? null)) {
                continue;
            }

            $current = $currentDialPlan[$rule['pattern']] ?? null;
            $preserved = is_array($current)
                ? array_diff_key($current, array_flip(['description', 'prefix', 'suffix']))
                : [];

            if ($preserved !== []) {
                $data['dial_plan']['rules'][$index]['preserved_options'] = $this->withoutRedactedValues($preserved);
            }
        }

        $currentFormatters = is_array($snapshot['formatters'] ?? null) ? $snapshot['formatters'] : [];
        $occurrences = [];

        foreach ($data['formatters'] ?? [] as $index => $formatter) {
            $field = is_array($formatter) ? ($formatter['field'] ?? null) : null;

            if (! is_string($field)) {
                continue;
            }

            $occurrence = $occurrences[$field] ?? 0;
            $occurrences[$field] = $occurrence + 1;
            $stored = $currentFormatters[$field] ?? null;
            $storedRules = is_array($stored) && array_is_list($stored) ? $stored : [$stored];
            $current = $storedRules[$occurrence] ?? null;
            $preserved = is_array($current)
                ? array_diff_key($current, array_flip([
                    'direction',
                    'match_invite_format',
                    'prefix',
                    'regex',
                    'strip',
                    'suffix',
                    'value',
                ]))
                : [];

            if ($preserved !== []) {
                $data['formatters'][$index]['preserved_options'] = $this->withoutRedactedValues($preserved);
            }
        }

        if (isset($data['metaflows']) && is_array($data['metaflows'])) {
            $currentMetaflows = is_array($snapshot['metaflows'] ?? null)
                ? $snapshot['metaflows']
                : [];
            $preserved = array_diff_key(
                $currentMetaflows,
                array_flip(['binding_digit', 'digit_timeout', 'listen_on']),
            );

            if (isset($data['metaflows']['actions']) && is_array($data['metaflows']['actions'])) {
                $maps = $this->metaflowPolicy->merge(
                    $currentMetaflows,
                    $data['metaflows']['actions'],
                    $account,
                );
                $preserved['numbers'] = $maps['numbers'];
                $preserved['patterns'] = $maps['patterns'];
                unset($data['metaflows']['actions']);
            }

            if ($preserved !== []) {
                $data['metaflows']['preserved_options'] = $this->withoutRedactedValues($preserved);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function withoutRedactedValues(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if ($value === '[REDACTED]') {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->withoutRedactedValues($value) : $value;
        }

        return $clean;
    }
}
