<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Blacklists\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class BlacklistSnapshot extends EntitySnapshot
{
    public string $name;

    public bool $shouldBlockAnonymous;

    /** @var array<string, array<string, mixed>> */
    public array $numbers;

    /** @var list<string> */
    public array $flags;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $name = $data['name'] ?? null;
        if (! is_string($name) || trim($name) === '') {
            throw new InvalidSwitchPayloadException('Switch blacklist response is missing its name.');
        }

        $numbers = $data['numbers'] ?? [];
        if (! is_array($numbers)) {
            throw new InvalidSwitchPayloadException('Switch blacklist numbers must be an object.');
        }

        $this->name = $name;
        $this->shouldBlockAnonymous = ($data['should_block_anonymous'] ?? false) === true;
        $this->flags = $this->stringList($data['flags'] ?? []);
        $normalizedNumbers = [];
        foreach ($numbers as $number => $metadata) {
            if (! is_string($number) || $number === '') {
                throw new InvalidSwitchPayloadException('Switch blacklist number keys must be non-empty strings.');
            }
            $normalizedNumbers[$number] = is_array($metadata) ? $metadata : [];
        }
        $this->numbers = $normalizedNumbers;
    }
}
