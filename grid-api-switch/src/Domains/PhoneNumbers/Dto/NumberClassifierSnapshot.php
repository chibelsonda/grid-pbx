<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\PhoneNumbers\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class NumberClassifierSnapshot
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $key,
        array $data,
    ) {
        if ($key === '') {
            throw new InvalidSwitchPayloadException('Switch number classifier key must be a non-empty string.');
        }

        $friendlyName = $data['friendly_name'] ?? null;

        if (! is_string($friendlyName) || $friendlyName === '') {
            throw new InvalidSwitchPayloadException('Switch number classifier must contain a friendly name.');
        }

        $this->friendlyName = $friendlyName;
        $this->emergency = ($data['emergency'] ?? false) === true;
    }

    public string $friendlyName;

    public bool $emergency;
}
