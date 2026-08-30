<?php

declare(strict_types=1);

namespace GridPbx\Switch\Shared\Authentication;

use GridPbx\Switch\Shared\Capabilities\CapabilityProvider;
use GridPbx\Switch\Shared\Exceptions\SwitchAuthenticationException;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\ClientInterface;
use JsonException;
use Throwable;

final class ApiKeyTokenProvider implements CapabilityProvider, TokenProvider
{
    private ?string $token = null;

    /** @var array<string, mixed>|null */
    private ?array $capabilities = null;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly SwitchConfig $config,
    ) {}

    public function token(): string
    {
        $this->authenticate();

        return $this->token
            ?? throw new SwitchAuthenticationException('Switch did not return an authentication token.');
    }

    /** @return array{available: bool|null, default: bool|null} */
    public function capability(string $path): array
    {
        $this->authenticate();
        $value = $this->capabilities;

        foreach (explode('.', $path) as $segment) {
            $value = is_array($value) ? ($value[$segment] ?? null) : null;
        }

        return [
            'available' => is_array($value) && is_bool($value['available'] ?? null)
                ? $value['available']
                : null,
            'default' => is_array($value) && is_bool($value['default'] ?? null)
                ? $value['default']
                : null,
        ];
    }

    public function invalidate(): void
    {
        $this->token = null;
        $this->capabilities = null;
    }

    private function authenticate(): void
    {
        if ($this->token !== null) {
            return;
        }

        try {
            $response = $this->http->request('PUT', $this->config->url('api_auth'), [
                'connect_timeout' => $this->config->timeout,
                'timeout' => $this->config->timeout,
                'headers' => ['Accept' => 'application/json'],
                'json' => ['data' => ['api_key' => $this->config->apiKey]],
            ]);

            /** @var array<string, mixed> $payload */
            $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SwitchAuthenticationException(
                'Switch returned an invalid authentication response.',
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new SwitchAuthenticationException(
                'Switch authentication failed.',
                previous: $exception,
            );
        }

        $token = $payload['auth_token'] ?? null;

        if (($payload['status'] ?? null) !== 'success' || ! is_string($token) || $token === '') {
            throw new SwitchAuthenticationException('Switch did not return an authentication token.');
        }

        $this->token = $token;
        $this->capabilities = is_array($payload['data']['capabilities'] ?? null)
            ? $payload['data']['capabilities']
            : [];
    }
}
