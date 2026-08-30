<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\CallerIdLists\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class CallerIdListEntrySnapshot extends EntitySnapshot
{
    public string $listId;

    public ?string $displayName;

    public ?string $number;

    public ?string $pattern;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $listId = $this->nullableString($data['list_id'] ?? null);

        if ($listId === null) {
            throw new InvalidSwitchPayloadException('Switch Caller-ID List entry is missing its list identifier.');
        }

        $this->listId = $listId;
        $this->displayName = $this->nullableString($data['displayname'] ?? null);
        $this->number = $this->nullableString($data['number'] ?? null);
        $this->pattern = $this->nullableString($data['pattern'] ?? null);
    }
}
