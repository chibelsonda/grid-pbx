<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\CallerIdLists\Dto;

use InvalidArgumentException;

final readonly class CallerIdListEntryWriteData
{
    public function __construct(
        public ?string $displayName,
        public ?string $number,
        public ?string $pattern,
    ) {
        if ($this->number === null && $this->pattern === null) {
            throw new InvalidArgumentException('A Switch Caller-ID List entry requires a number or pattern.');
        }

        if ($this->number !== null && preg_match('/^\+?[0-9]{1,32}$/', $this->number) !== 1) {
            throw new InvalidArgumentException('Switch Caller-ID List numbers must contain only an optional plus sign and digits.');
        }

        if ($this->pattern !== null && (mb_strlen($this->pattern) > 512 || str_contains($this->pattern, "\r") || str_contains($this->pattern, "\n"))) {
            throw new InvalidArgumentException('Switch Caller-ID List patterns must be safe single-line values up to 512 characters.');
        }

        if ($this->displayName !== null && (trim($this->displayName) === '' || mb_strlen($this->displayName) > 128)) {
            throw new InvalidArgumentException('Switch Caller-ID List entry display name must contain 1 to 128 characters.');
        }
    }

    /** @return array<string, string> */
    public function toSwitchData(): array
    {
        return array_filter([
            'displayname' => $this->displayName === null ? null : trim($this->displayName),
            'number' => $this->number,
            'pattern' => $this->pattern,
        ], static fn (?string $value): bool => $value !== null);
    }
}
