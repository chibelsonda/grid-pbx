<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Directories\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class DirectorySnapshot extends EntitySnapshot
{
    public string $name;

    public bool $confirmMatch;

    public int $minDtmf;

    public int $maxDtmf;

    public string $sortBy;

    /** @var list<string> */
    public array $flags;

    /** @var list<DirectoryMemberSnapshot> */
    public array $members;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $name = $data['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw new InvalidSwitchPayloadException('Switch directory must contain a non-empty name.');
        }

        $this->name = $name;
        $this->confirmMatch = (bool) ($data['confirm_match'] ?? true);
        $this->minDtmf = is_int($data['min_dtmf'] ?? null) ? max(1, $data['min_dtmf']) : 3;
        $this->maxDtmf = is_int($data['max_dtmf'] ?? null) ? max(0, $data['max_dtmf']) : 0;
        $this->sortBy = in_array($data['sort_by'] ?? null, ['first_name', 'last_name'], true)
            ? $data['sort_by']
            : 'last_name';
        $this->flags = $this->stringList($data['flags'] ?? []);
        $members = $data['users'] ?? [];

        if (! is_array($members)) {
            throw new InvalidSwitchPayloadException('Switch directory users must be an array.');
        }

        $this->members = array_map(
            static fn (mixed $member): DirectoryMemberSnapshot => is_array($member)
                ? new DirectoryMemberSnapshot($member)
                : throw new InvalidSwitchPayloadException('Switch directory users must contain objects.'),
            array_values($members),
        );
    }
}
