<?php

declare(strict_types=1);

namespace GridPbx\Switch;

use GridPbx\Switch\Contracts\TokenProvider;
use GridPbx\Switch\Exceptions\SwitchRequestException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonException;

final class SwitchClient
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly SwitchConfig $config,
        private readonly TokenProvider $tokens,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $options = []): array
    {
        return $this->send($method, $path, $options, true);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $options, bool $retryAuthentication): array
    {
        $options['connect_timeout'] ??= $this->config->timeout;
        $options['timeout'] ??= $this->config->timeout;
        $options['headers'] = array_merge(
            ['Accept' => 'application/json'],
            $options['headers'] ?? [],
            ['X-Auth-Token' => $this->tokens->token()],
        );

        try {
            $response = $this->http->request($method, $this->config->url($path), $options);
            /** @var array<string, mixed> $payload */
            $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (RequestException $exception) {
            $status = $exception->getResponse()?->getStatusCode() ?? 502;

            if ($status === 401 && $retryAuthentication) {
                $this->tokens->invalidate();

                return $this->send($method, $path, $options, false);
            }

            throw new SwitchRequestException('Switch request failed.', $status, previous: $exception);
        } catch (JsonException $exception) {
            throw new SwitchRequestException('Switch returned invalid JSON.', 502, previous: $exception);
        } catch (GuzzleException $exception) {
            throw new SwitchRequestException('Switch is unavailable.', 502, previous: $exception);
        }

        if (($payload['status'] ?? 'success') === 'error') {
            $message = is_string($payload['message'] ?? null)
                ? $payload['message']
                : 'Switch returned an error.';

            throw new SwitchRequestException($message, $response->getStatusCode(), $payload);
        }

        return $payload;
    }
}
