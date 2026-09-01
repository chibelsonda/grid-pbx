<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Enums\CallflowIntegrationType;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Validation\ValidationException;

/**
 * Resolves public Pivot profile UUIDs to administrator-owned Switch settings.
 *
 * Raw callback URLs and authentication headers never cross the API boundary. A
 * valid active account profile is the capability boundary.
 */
class PivotEndpointRegistry
{
    /** @var list<string> */
    private const PROFILE_SETTING_KEYS = [
        'voice_url',
        'cdr_url',
        'methods',
        'formats',
        'req_body_format',
        'req_timeout_ms',
        'debug',
        'custom_request_headers',
    ];

    public function __construct(private readonly CallflowHttpsEndpointPolicy $httpsEndpoints) {}

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    public function validatedProfileSettings(array $settings): array
    {
        $normalized = $this->normalizeConfiguration($settings);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'settings' => ['The Pivot profile contains an unsafe or unsupported configuration.'],
            ]);
        }

        return $normalized;
    }

    /** @return list<array{id: string, label: string, methods: list<string>, formats: list<string>}> */
    public function publicEndpoints(SwitchAccount $account): array
    {
        return array_values(array_map(
            fn (array $endpoint): array => [
                'id' => $endpoint['id'],
                'label' => $endpoint['label'],
                'methods' => $endpoint['methods'],
                'formats' => $endpoint['formats'],
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
                'reason' => 'No active administrator-approved Pivot profile is configured for this account.',
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
                'data.endpoint_id' => ['Select an administrator-approved Pivot endpoint.'],
            ]);
        }

        $method = $settings['method'] ?? null;
        $format = $settings['req_format'] ?? null;

        if (! is_string($method) || ! in_array($method, $endpoint['methods'], true)) {
            throw ValidationException::withMessages([
                'data.method' => ['Select a request method allowed by this Pivot endpoint.'],
            ]);
        }

        if (! is_string($format) || ! in_array($format, $endpoint['formats'], true)) {
            throw ValidationException::withMessages([
                'data.req_format' => ['Select a response format allowed by this Pivot endpoint.'],
            ]);
        }

        return array_filter([
            'voice_url' => $endpoint['voice_url'],
            'method' => $method,
            'req_format' => $format,
            'req_body_format' => $endpoint['req_body_format'],
            'cdr_url' => $endpoint['cdr_url'],
            'debug' => $endpoint['debug'],
            'req_timeout_ms' => $endpoint['req_timeout_ms'],
            'custom_request_headers' => $endpoint['custom_request_headers'],
            'skip_module' => ($settings['skip_module'] ?? false) === true,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function publicSettings(SwitchAccount $account, array $data): array
    {
        $method = is_string($data['method'] ?? null) ? strtolower($data['method']) : 'get';
        $format = is_string($data['req_format'] ?? null) ? strtolower($data['req_format']) : 'kazoo';
        $endpoint = $this->findMatchingEndpoint($account, $data['voice_url'] ?? null, $method, $format);
        $supported = $endpoint !== null;

        return [
            'supported_configuration' => $supported,
            'endpoint_id' => $supported ? $endpoint['id'] : null,
            'endpoint_label' => $supported ? $endpoint['label'] : null,
            'method' => $supported ? $method : null,
            'req_format' => $supported ? $format : null,
            'skip_module' => (bool) ($data['skip_module'] ?? false),
        ];
    }

    /** @return array<string, mixed>|null */
    private function findById(SwitchAccount $account, mixed $id): ?array
    {
        if (! is_string($id)) {
            return null;
        }

        foreach ($this->endpoints($account) as $endpoint) {
            if ($endpoint['id'] === $id) {
                return $endpoint;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function findMatchingEndpoint(
        SwitchAccount $account,
        mixed $url,
        string $method,
        string $format,
    ): ?array {
        if (! is_string($url)) {
            return null;
        }

        foreach ($this->endpoints($account) as $endpoint) {
            if (hash_equals($endpoint['voice_url'], $url)
                && in_array($method, $endpoint['methods'], true)
                && in_array($format, $endpoint['formats'], true)) {
                return $endpoint;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function endpoints(SwitchAccount $account): array
    {
        return $account->callflowIntegrationProfiles()
            ->where('integration_type', CallflowIntegrationType::Pivot->value)
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
        $endpoint = $profile->settings;

        if (! is_array($endpoint)) {
            return null;
        }

        $id = $profile->id;
        $label = $profile->name;
        $configuration = $this->normalizeConfiguration($endpoint);

        if ($configuration === null) {
            return null;
        }

        return [
            'id' => $id,
            'label' => trim($label),
            ...$configuration,
        ];
    }

    /** @param array<string, mixed> $endpoint @return array<string, mixed>|null */
    private function normalizeConfiguration(array $endpoint): ?array
    {
        if (array_diff(array_keys($endpoint), self::PROFILE_SETTING_KEYS) !== []) {
            return null;
        }

        $voiceUrl = $endpoint['voice_url'] ?? null;
        $cdrUrl = $endpoint['cdr_url'] ?? null;
        $methods = $this->allowedValues($endpoint['methods'] ?? ['get'], ['get', 'post']);
        $formats = $this->allowedValues($endpoint['formats'] ?? ['kazoo'], ['kazoo', 'twiml']);
        $bodyFormat = $endpoint['req_body_format'] ?? 'form';
        $timeout = $endpoint['req_timeout_ms'] ?? 5000;
        $debug = $endpoint['debug'] ?? false;
        $headers = $endpoint['custom_request_headers'] ?? [];

        if (! $this->httpsEndpoints->allows($voiceUrl)
            || ($cdrUrl !== null && ! $this->httpsEndpoints->allows($cdrUrl))
            || $methods === []
            || $formats === []
            || ! in_array($bodyFormat, ['form', 'json'], true)
            || ! is_int($timeout)
            || $timeout < 1
            || $timeout > 5000
            || $debug !== false
            || ! $this->headersAreSafe($headers)) {
            return null;
        }

        return [
            'voice_url' => $voiceUrl,
            'cdr_url' => $cdrUrl,
            'methods' => $methods,
            'formats' => $formats,
            'req_body_format' => $bodyFormat,
            'req_timeout_ms' => $timeout,
            'debug' => false,
            'custom_request_headers' => $headers,
        ];
    }

    /** @param mixed $values @param list<string> $allowed @return list<string> */
    private function allowedValues(mixed $values, array $allowed): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $value): ?string => is_string($value) ? strtolower($value) : null,
            $values,
        ), fn (?string $value): bool => $value !== null && in_array($value, $allowed, true))));
    }

    private function headersAreSafe(mixed $headers): bool
    {
        if (! is_array($headers)
            || ($headers !== [] && array_is_list($headers))
            || count($headers) > 20) {
            return false;
        }

        $normalizedNames = [];

        foreach ($headers as $name => $value) {
            if (! is_string($name)
                || preg_match('/^X-[A-Za-z0-9-]{1,62}$/', $name) !== 1
                || ! is_string($value)
                || strlen($value) > 1024
                || preg_match('/[\x00\r\n]/', $value) === 1) {
                return false;
            }

            $normalizedName = strtolower($name);

            if (isset($normalizedNames[$normalizedName])) {
                return false;
            }

            $normalizedNames[$normalizedName] = true;
        }

        return true;
    }
}
