<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Validation\ValidationException;

/**
 * Resolves public Webhook profile UUIDs to administrator-owned Switch settings.
 *
 * The browser never receives the target URI. Per-node data is limited to the
 * installed Switch schema and an endpoint's administrator-approved limits.
 */
final class WebhookEndpointRegistry
{
    /** @var list<string> */
    private const PROFILE_SETTING_KEYS = ['uri', 'methods', 'max_retries'];

    public function __construct(private readonly CallflowHttpsEndpointPolicy $httpsEndpoints) {}

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    public function validatedProfileSettings(array $settings): array
    {
        $normalized = $this->normalizeConfiguration($settings);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'settings' => ['The Webhook profile contains an unsafe or unsupported configuration.'],
            ]);
        }

        return $normalized;
    }

    /** @return list<array{id: string, label: string, methods: list<string>, max_retries: int}> */
    public function publicEndpoints(SwitchAccount $account): array
    {
        return array_values(array_map(
            fn (array $endpoint): array => [
                'id' => $endpoint['id'],
                'label' => $endpoint['label'],
                'methods' => $endpoint['methods'],
                'max_retries' => $endpoint['max_retries'],
            ],
            $this->endpoints($account),
        ));
    }

    /** @return array{enabled: bool, reason: ?string} */
    public function capability(SwitchAccount $account): array
    {
        if ($this->endpoints($account) === []) {
            return [
                'enabled' => false,
                'reason' => 'No active administrator-approved Webhook profile is configured for this account.',
            ];
        }

        return ['enabled' => true, 'reason' => null];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    public function settingsForSwitch(SwitchAccount $account, array $settings): array
    {
        $capability = $this->capability($account);

        if (! $capability['enabled']) {
            throw ValidationException::withMessages(['module' => [$capability['reason']]]);
        }

        $endpoint = $this->findById($account, $settings['endpoint_id'] ?? null);

        if ($endpoint === null) {
            throw ValidationException::withMessages([
                'data.endpoint_id' => ['Select an administrator-approved Webhook endpoint.'],
            ]);
        }

        $method = is_string($settings['http_verb'] ?? null)
            ? strtolower($settings['http_verb'])
            : null;
        $retries = $settings['retries'] ?? null;
        $customData = $settings['custom_data'] ?? [];

        if ($method === null || ! in_array($method, $endpoint['methods'], true)) {
            throw ValidationException::withMessages([
                'data.http_verb' => ['Select a request method allowed by this Webhook endpoint.'],
            ]);
        }

        if (! is_int($retries) || $retries < 1 || $retries > $endpoint['max_retries']) {
            throw ValidationException::withMessages([
                'data.retries' => ["Retries must be between 1 and {$endpoint['max_retries']} for this endpoint."],
            ]);
        }

        if (! $this->customDataIsSafe($customData)) {
            throw ValidationException::withMessages([
                'data.custom_data' => ['Webhook custom data must contain at most 20 safe scalar values.'],
            ]);
        }

        return [
            'uri' => $endpoint['uri'],
            'http_verb' => $method,
            'retries' => $retries,
            'custom_data' => $customData === [] ? (object) [] : $customData,
            'skip_module' => ($settings['skip_module'] ?? false) === true,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function publicSettings(SwitchAccount $account, array $data): array
    {
        $method = is_string($data['http_verb'] ?? null) ? strtolower($data['http_verb']) : 'post';
        $retries = is_int($data['retries'] ?? null) ? $data['retries'] : 1;
        $customData = is_array($data['custom_data'] ?? null) ? $data['custom_data'] : [];
        $endpoint = $this->findMatchingEndpoint($account, $data['uri'] ?? null, $method, $retries);
        $supported = $endpoint !== null && $this->customDataIsSafe($customData);

        return [
            'supported_configuration' => $supported,
            'endpoint_id' => $supported ? $endpoint['id'] : null,
            'endpoint_label' => $supported ? $endpoint['label'] : null,
            'http_verb' => $supported ? $method : null,
            'retries' => $supported ? $retries : null,
            'custom_data' => $supported ? $customData : [],
            'skip_module' => (bool) ($data['skip_module'] ?? false),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function endpoints(SwitchAccount $account): array
    {
        return $account->callflowIntegrationProfiles()
            ->where('integration_type', CallflowIntegrationType::Webhook->value)
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

        $uri = $settings['uri'] ?? null;
        $methods = $this->allowedMethods($settings['methods'] ?? ['post']);
        $maxRetries = $settings['max_retries'] ?? 3;

        if (! $this->httpsEndpoints->allows($uri)
            || $methods === []
            || ! is_int($maxRetries)
            || $maxRetries < 1
            || $maxRetries > 5) {
            return null;
        }

        return [
            'uri' => $uri,
            'methods' => $methods,
            'max_retries' => $maxRetries,
        ];
    }

    /** @return list<string> */
    private function allowedMethods(mixed $methods): array
    {
        if (! is_array($methods) || ! array_is_list($methods)) {
            return [];
        }

        $normalized = [];

        foreach ($methods as $method) {
            $value = is_string($method) ? strtolower($method) : null;

            if ($value === null || ! in_array($value, ['get', 'post'], true)) {
                return [];
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    private function findById(SwitchAccount $account, mixed $id): ?array
    {
        if (! is_string($id)) {
            return null;
        }

        return collect($this->endpoints($account))->first(
            fn (array $endpoint): bool => $endpoint['id'] === $id,
        );
    }

    private function findMatchingEndpoint(
        SwitchAccount $account,
        mixed $uri,
        string $method,
        int $retries,
    ): ?array {
        if (! is_string($uri)) {
            return null;
        }

        return collect($this->endpoints($account))->first(
            fn (array $endpoint): bool => hash_equals($endpoint['uri'], $uri)
                && in_array($method, $endpoint['methods'], true)
                && $retries >= 1
                && $retries <= $endpoint['max_retries'],
        );
    }

    private function customDataIsSafe(mixed $customData): bool
    {
        if (! is_array($customData)
            || ($customData !== [] && array_is_list($customData))
            || count($customData) > 20) {
            return false;
        }

        foreach ($customData as $key => $value) {
            if (! is_string($key)
                || preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $key) !== 1
                || (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value))
                || (is_string($value) && (strlen($value) > 1024 || preg_match('/[\x00\r\n]/', $value) === 1))) {
                return false;
            }
        }

        return true;
    }
}
