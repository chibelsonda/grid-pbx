<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\CallerIdLists\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class CallerIdListSnapshot extends EntitySnapshot
{
    public string $name;

    public ?string $description;

    public ?string $organization;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $name = $this->nullableString($data['name'] ?? null);

        if ($name === null) {
            throw new InvalidSwitchPayloadException('Switch Caller-ID List response is missing its name.');
        }

        $this->name = $name;
        $this->description = $this->nullableString($data['description'] ?? null);
        $this->organization = $this->nullableString($data['org'] ?? null);
    }
}
