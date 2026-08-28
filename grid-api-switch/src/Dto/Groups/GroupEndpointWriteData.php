<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Groups;

use InvalidArgumentException;

final readonly class GroupEndpointWriteData
{
    public function __construct(
        public string $resourceId,
        public string $type,
        public int $weight,
    ) {
        if ($this->resourceId === '' || ! in_array($this->type, ['user', 'device', 'group'], true)) {
            throw new InvalidArgumentException('Switch group endpoint is invalid.');
        }

        if ($this->weight < 1 || $this->weight > 100) {
            throw new InvalidArgumentException('Switch group endpoint weight must be between 1 and 100.');
        }
    }
}
