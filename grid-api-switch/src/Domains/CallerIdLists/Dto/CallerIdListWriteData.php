<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\CallerIdLists\Dto;

use InvalidArgumentException;

final readonly class CallerIdListWriteData
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $organization = null,
    ) {
        if (trim($this->name) === '' || mb_strlen($this->name) > 128) {
            throw new InvalidArgumentException('Switch Caller-ID List name must contain 1 to 128 characters.');
        }

        if ($this->description !== null && (trim($this->description) === '' || mb_strlen($this->description) > 128)) {
            throw new InvalidArgumentException('Switch Caller-ID List description must contain 1 to 128 characters.');
        }
    }

    /** @return array<string, string> */
    public function toSwitchData(): array
    {
        return array_filter([
            'name' => trim($this->name),
            'description' => $this->description === null ? null : trim($this->description),
            'org' => $this->organization === null ? null : trim($this->organization),
        ], static fn (?string $value): bool => $value !== null);
    }
}
