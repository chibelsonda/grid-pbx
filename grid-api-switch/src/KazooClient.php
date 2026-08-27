<?php

declare(strict_types=1);

namespace GridPbx\Kazoo;

use GridPbx\Kazoo\Contracts\TokenProvider;
use GridPbx\Kazoo\Exceptions\KazooRequestException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonException;

final class KazooClient
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly KazooConfig $config,
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
        $options['headers'] = array_merge([
            'Accept' => 'application/json',
            'X-Auth-Token' => $this->tokens->token(),
        ], $options['headers'] ?? []);

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

            throw new KazooRequestException('Kazoo request failed.', $status, previous: $exception);
        } catch (JsonException $exception) {
            throw new KazooRequestException('Kazoo returned invalid JSON.', 502, previous: $exception);
        } catch (GuzzleException $exception) {
            throw new KazooRequestException('Kazoo is unavailable.', 502, previous: $exception);
        }

        if (($payload['status'] ?? 'success') === 'error') {
            $message = is_string($payload['message'] ?? null)
                ? $payload['message']
                : 'Kazoo returned an error.';

            throw new KazooRequestException($message, $response->getStatusCode(), $payload);
        }

        return $payload;
    }
}
