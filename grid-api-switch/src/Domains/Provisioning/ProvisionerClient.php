<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Provisioning;

use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonException;

final readonly class ProvisionerClient
{
    public function __construct(
        private ClientInterface $http,
        private ProvisionerConfig $config,
    ) {}

    /** @return array<string, mixed> */
    public function get(string $path): array
    {
        $options = [
            'connect_timeout' => $this->config->timeout,
            'timeout' => $this->config->timeout,
            'verify' => $this->config->verifyTls,
            'headers' => array_merge(['Accept' => 'application/json'], $this->config->headers()),
        ];

        if (($authentication = $this->config->basicAuthentication()) !== null) {
            $options['auth'] = $authentication;
        }

        try {
            $response = $this->http->request('GET', $this->config->url($path), $options);
            $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (RequestException $exception) {
            throw new SwitchRequestException(
                'Provisioner request failed.',
                $exception->getResponse()?->getStatusCode() ?? 502,
                previous: $exception,
            );
        } catch (JsonException $exception) {
            throw new SwitchRequestException('Provisioner returned invalid JSON.', 502, previous: $exception);
        } catch (GuzzleException $exception) {
            throw new SwitchRequestException('Provisioner is unavailable.', 502, previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new SwitchRequestException('Provisioner returned an invalid response.', 502);
        }

        return $decoded;
    }
}
