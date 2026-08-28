<?php

declare(strict_types=1);

namespace GridPbx\Switch;

use InvalidArgumentException;

final readonly class SwitchConfig
{
    public function __construct(
        public string $baseUrl,
        public string $apiKey,
        public float $timeout = 10.0,
    ) {
        if ($this->baseUrl === '') {
            throw new InvalidArgumentException('Switch base URL is required.');
        }

        if ($this->apiKey === '') {
            throw new InvalidArgumentException('Switch API key is required.');
        }

        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('Switch timeout must be greater than zero.');
        }
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}
