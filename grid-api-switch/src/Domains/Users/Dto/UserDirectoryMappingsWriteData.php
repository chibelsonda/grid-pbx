<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto;

use InvalidArgumentException;
use stdClass;

final readonly class UserDirectoryMappingsWriteData
{
    /** @param array<string, string> $mappings */
    public function __construct(public array $mappings)
    {
        foreach ($this->mappings as $directoryId => $callflowId) {
            if (! is_string($directoryId) || $directoryId === '' || ! is_string($callflowId) || $callflowId === '') {
                throw new InvalidArgumentException('Switch user directory mappings must contain valid identifiers.');
            }
        }
    }

    /** @return array{directories: array<string, string>|stdClass} */
    public function toSwitchData(): array
    {
        return ['directories' => $this->mappings === [] ? new stdClass : $this->mappings];
    }
}
