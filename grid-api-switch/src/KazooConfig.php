<?php

declare(strict_types=1);

namespace GridPbx\Kazoo;

use InvalidArgumentException;

final readonly class KazooConfig
{
    public function __construct(
        public string $baseUrl,
        public string $apiKey,
        public float $timeout = 10.0,
    ) {
        if ($this->baseUrl === '') {
            throw new InvalidArgumentException('Kazoo base URL is required.');
        }

        if ($this->apiKey === '') {
            throw new InvalidArgumentException('Kazoo API key is required.');
        }

        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('Kazoo timeout must be greater than zero.');
        }
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}
