<?php

declare(strict_types=1);

namespace GridPbx\Switch\Http;

use Psr\Http\Message\StreamInterface;

final readonly class BinaryResponse
{
    public function __construct(
        public StreamInterface $stream,
        public int $statusCode,
        public string $contentType,
        public ?int $contentLength = null,
        public ?string $contentRange = null,
    ) {}
}
