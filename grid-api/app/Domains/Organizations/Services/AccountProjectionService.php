<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Illuminate\Support\Arr;
use UnexpectedValueException;

class AccountProjectionService
{
    public function __construct(private readonly RedactSensitiveSwitchData $redactSensitiveData) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchAccount
    {
        if (($snapshot['id'] ?? null) !== $account->switch_account_id) {
            throw new UnexpectedValueException('Switch returned an unexpected account identifier.');
        }

        $account->fill([
            'name' => $this->string($snapshot['name'] ?? null) ?? $account->name,
            'org_name' => $this->string($snapshot['org'] ?? null),
            'realm' => $this->string($snapshot['realm'] ?? null),
            'timezone' => $this->string($snapshot['timezone'] ?? null),
            'language' => $this->string($snapshot['language'] ?? null),
            'is_enabled' => ($snapshot['enabled'] ?? true) !== false,
            'is_reseller' => array_key_exists('is_reseller', $snapshot)
                ? $snapshot['is_reseller'] === true
                : $account->is_reseller,
            'is_superduper_admin' => array_key_exists('superduper_admin', $snapshot)
                ? $snapshot['superduper_admin'] === true
                : $account->is_superduper_admin,
            'billing_mode' => array_key_exists('billing_mode', $snapshot)
                ? $this->string($snapshot['billing_mode'])
                : $account->billing_mode,
            'descendants_count' => is_numeric($snapshot['descendants_count'] ?? null)
                ? max(0, (int) $snapshot['descendants_count'])
                : max(0, (int) ($account->descendants_count ?? 0)),
            'call_waiting_enabled' => Arr::get($snapshot, 'call_waiting.enabled', true) !== false,
            'do_not_disturb_enabled' => Arr::get($snapshot, 'do_not_disturb.enabled', false) === true,
            'outbound_privacy' => $this->string(Arr::get($snapshot, 'caller_id_options.outbound_privacy')),
            'ringtone_internal' => $this->string(Arr::get($snapshot, 'ringtones.internal')),
            'ringtone_external' => $this->string(Arr::get($snapshot, 'ringtones.external')),
            'last_synced_at' => now(),
            'sync_status' => 'synced',
            'projection_version' => $account->projection_version + 1,
            'switch_json' => $this->redactSensitiveData->handle($snapshot),
        ])->save();

        return $account->refresh();
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
