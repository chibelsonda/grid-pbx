<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Contracts\DisaOperationalGuard;
use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Validation\ValidationException;

/**
 * Keeps DISA credentials server-side and projects only a bounded native policy.
 *
 * A Callflow stores the profile's public UUID. The PIN and the fixed restriction
 * controls are resolved immediately before the Switch write and are never
 * returned through the public editor contract.
 */
final class DisaAccessPolicyRegistry
{
    public function __construct(private readonly DisaOperationalGuard $operationalGuard) {}

    /** @var list<string> */
    private const PROFILE_SETTING_KEYS = [
        'pin',
        'retries',
        'interdigit_ms',
        'max_digits',
        'preconnect_audio',
    ];

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    public function validatedProfileSettings(array $settings): array
    {
        $normalized = $this->normalizeConfiguration($settings);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'settings' => ['The DISA access policy contains an unsafe or unsupported configuration.'],
            ]);
        }

        return $normalized;
    }

    /** @return list<array{id: string, label: string, retries: int, interdigit_ms: int, max_digits: int, preconnect_audio: string}> */
    public function publicPolicies(SwitchAccount $account): array
    {
        return array_values(array_map(
            fn (array $policy): array => [
                'id' => $policy['id'],
                'label' => $policy['label'],
                'retries' => $policy['retries'],
                'interdigit_ms' => $policy['interdigit_ms'],
                'max_digits' => $policy['max_digits'],
                'preconnect_audio' => $policy['preconnect_audio'],
            ],
            $this->policies($account),
        ));
    }

    /** @return array{enabled: bool, reason: ?string} */
    public function capability(SwitchAccount $account): array
    {
        if ($this->policies($account) === []) {
            return [
                'enabled' => false,
                'reason' => 'No active administrator-approved DISA access policy is configured for this account.',
            ];
        }

        $readiness = $this->operationalGuard->inspect($account);

        if (! $readiness->ready()) {
            return [
                'enabled' => false,
                'reason' => $readiness->reason
                    ?? 'The DISA ingress guard does not report every required operational control.',
            ];
        }

        return ['enabled' => true, 'reason' => null];
    }

    /** @return array<string, bool|string|null> */
    public function operationalStatus(SwitchAccount $account): array
    {
        return $this->operationalGuard->inspect($account)->toPublicArray();
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    public function settingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $capability = $this->capability($account);

        if (! $capability['enabled']) {
            throw ValidationException::withMessages(['module' => [$capability['reason']]]);
        }

        $policy = $this->findById($account, $settings['access_policy_id'] ?? null);

        if ($policy === null) {
            throw ValidationException::withMessages([
                'data.access_policy_id' => ['Select an administrator-approved DISA access policy.'],
            ]);
        }

        return [
            'pin' => $policy['pin'],
            'retries' => $policy['retries'],
            'interdigit' => $policy['interdigit_ms'],
            'max_digits' => $policy['max_digits'],
            'preconnect_audio' => $policy['preconnect_audio'],
            // These controls are intentionally fixed and cannot be weakened by the browser.
            'use_account_caller_id' => false,
            'enforce_call_restriction' => true,
            'skip_module' => ($settings['skip_module'] ?? false) === true,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function publicSettings(SwitchAccount $account, array $data): array
    {
        $policy = $this->findMatchingPolicy($account, $data);
        $supported = $this->capability($account)['enabled']
            && $policy !== null
            && ($data['use_account_caller_id'] ?? false) === false
            && ($data['enforce_call_restriction'] ?? false) === true;

        return [
            'supported_configuration' => $supported,
            'access_policy_id' => $supported && $policy !== null ? $policy['id'] : null,
            'access_policy_label' => $supported && $policy !== null ? $policy['label'] : null,
            'skip_module' => (bool) ($data['skip_module'] ?? false),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function policies(SwitchAccount $account): array
    {
        return $account->callflowIntegrationProfiles()
            ->where('integration_type', CallflowIntegrationType::Disa->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('callflow_integration_profile_id')
            ->get()
            ->map(fn (CallflowIntegrationProfile $profile): ?array => $this->normalize($profile))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function normalize(CallflowIntegrationProfile $profile): ?array
    {
        $settings = $profile->settings;
        $configuration = is_array($settings) ? $this->normalizeConfiguration($settings) : null;

        if ($configuration === null) {
            return null;
        }

        return [
            'id' => $profile->id,
            'label' => trim($profile->name),
            ...$configuration,
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed>|null */
    private function normalizeConfiguration(array $settings): ?array
    {
        if (array_diff(array_keys($settings), self::PROFILE_SETTING_KEYS) !== []) {
            return null;
        }

        $pin = $settings['pin'] ?? null;
        $retries = $settings['retries'] ?? null;
        $interdigit = $settings['interdigit_ms'] ?? null;
        $maxDigits = $settings['max_digits'] ?? null;
        $preconnectAudio = $settings['preconnect_audio'] ?? null;

        if (! is_string($pin)
            || preg_match('/^\d{8,12}$/', $pin) !== 1
            || ! is_int($retries)
            || $retries < 1
            || $retries > 3
            || ! is_int($interdigit)
            || $interdigit < 1000
            || $interdigit > 5000
            || ! is_int($maxDigits)
            || $maxDigits < 3
            || $maxDigits > 15
            || ! in_array($preconnectAudio, ['dialtone', 'ringing'], true)) {
            return null;
        }

        return [
            'pin' => $pin,
            'retries' => $retries,
            'interdigit_ms' => $interdigit,
            'max_digits' => $maxDigits,
            'preconnect_audio' => $preconnectAudio,
        ];
    }

    /** @return array<string, mixed>|null */
    private function findById(SwitchAccount $account, mixed $id): ?array
    {
        if (! is_string($id)) {
            return null;
        }

        return collect($this->policies($account))->first(
            fn (array $policy): bool => $policy['id'] === $id,
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    private function findMatchingPolicy(SwitchAccount $account, array $data): ?array
    {
        foreach ($this->policies($account) as $policy) {
            if (is_string($data['pin'] ?? null)
                && hash_equals($policy['pin'], $data['pin'])
                && ($data['retries'] ?? null) === $policy['retries']
                && ($data['interdigit'] ?? null) === $policy['interdigit_ms']
                && ($data['max_digits'] ?? null) === $policy['max_digits']
                && ($data['preconnect_audio'] ?? null) === $policy['preconnect_audio']) {
                return $policy;
            }
        }

        return null;
    }
}
