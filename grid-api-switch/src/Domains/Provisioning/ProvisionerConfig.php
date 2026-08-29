<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Provisioning;

use InvalidArgumentException;

final readonly class ProvisionerConfig
{
    /** @var list<string> */
    private const AUTH_TYPES = ['none', 'bearer', 'basic', 'header'];

    public function __construct(
        public string $baseUrl,
        public string $authType = 'none',
        public ?string $token = null,
        public ?string $username = null,
        public ?string $password = null,
        public string $headerName = 'X-Auth-Token',
        public float $timeout = 10.0,
        public bool $verifyTls = true,
    ) {
        if (trim($this->baseUrl) === '') {
            throw new InvalidArgumentException('Provisioner base URL is required.');
        }

        if (! in_array($this->authType, self::AUTH_TYPES, true)) {
            throw new InvalidArgumentException('Provisioner authentication type is invalid.');
        }

        if (in_array($this->authType, ['bearer', 'header'], true) && trim((string) $this->token) === '') {
            throw new InvalidArgumentException('Provisioner authentication token is required.');
        }

        if ($this->authType === 'basic' && (trim((string) $this->username) === '' || $this->password === null)) {
            throw new InvalidArgumentException('Provisioner username and password are required.');
        }

        if ($this->authType === 'header' && ! preg_match('/^[A-Za-z0-9-]+$/', $this->headerName)) {
            throw new InvalidArgumentException('Provisioner authentication header name is invalid.');
        }

        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('Provisioner timeout must be greater than zero.');
        }
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return match ($this->authType) {
            'bearer' => ['Authorization' => 'Bearer '.$this->token],
            'header' => [$this->headerName => (string) $this->token],
            default => [],
        };
    }

    /** @return array{0: string, 1: string}|null */
    public function basicAuthentication(): ?array
    {
        return $this->authType === 'basic'
            ? [(string) $this->username, (string) $this->password]
            : null;
    }
}
