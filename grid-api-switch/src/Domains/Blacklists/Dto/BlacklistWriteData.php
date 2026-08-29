<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Blacklists\Dto;

use InvalidArgumentException;
use stdClass;

final readonly class BlacklistWriteData
{
    /** @param list<string> $numbers @param list<string> $flags */
    public function __construct(
        public string $name,
        public array $numbers = [],
        public bool $shouldBlockAnonymous = false,
        public array $flags = [],
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch blacklist name is required.');
        }
        foreach ($this->numbers as $number) {
            if (! is_string($number) || ! preg_match('/^\+[1-9]\d{6,14}$/', $number)) {
                throw new InvalidArgumentException('Switch blacklist numbers must use E.164 format.');
            }
        }
        if (count(array_unique($this->numbers)) !== count($this->numbers)) {
            throw new InvalidArgumentException('Switch blacklist numbers must be unique.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $numbers = [];
        foreach ($this->numbers as $number) {
            $numbers[$number] = new stdClass;
        }

        return [
            'name' => $this->name,
            'numbers' => $numbers === [] ? new stdClass : $numbers,
            'should_block_anonymous' => $this->shouldBlockAnonymous,
            'flags' => array_values(array_filter($this->flags, static fn (mixed $flag): bool => is_string($flag) && $flag !== '')),
        ];
    }
}
