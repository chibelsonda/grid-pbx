<?php

declare(strict_types=1);

namespace GridPbx\Switch\Shared\Authentication;

use GridPbx\Switch\Shared\Exceptions\SwitchAuthenticationException;
use GridPbx\Switch\SwitchConfig;
use GuzzleHttp\ClientInterface;
use JsonException;
use Throwable;

final class ApiKeyTokenProvider implements TokenProvider
{
    private ?string $token = null;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly SwitchConfig $config,
    ) {}

    public function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
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

        return $this->token = $token;
    }

    public function invalidate(): void
    {
        $this->token = null;
    }
}
