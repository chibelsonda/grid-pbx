<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Groups\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class GroupEndpointSnapshot
{
    public function __construct(
        public string $resourceId,
        public string $type,
        public int $weight,
    ) {
        if ($this->resourceId === '' || ! in_array($this->type, ['user', 'device', 'group'], true)) {
            throw new InvalidSwitchPayloadException('Switch group endpoint is invalid.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(string $resourceId, array $data): self
    {
        $type = $data['type'] ?? null;
        $weight = $data['weight'] ?? 1;

        if (! is_string($type) || ! is_int($weight) || $weight < 1) {
            throw new InvalidSwitchPayloadException('Switch group endpoint metadata is invalid.');
        }

        return new self($resourceId, $type, $weight);
    }
}
