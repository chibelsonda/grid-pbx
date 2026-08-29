<?php

declare(strict_types=1);

namespace GridPbx\Switch\Shared\Exceptions;

use Throwable;

final class SwitchRequestException extends SwitchException
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly array $payload = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
