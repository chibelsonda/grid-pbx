<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Agents\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;

final readonly class AgentSnapshot extends EntitySnapshot
{
    public ?string $firstName;

    public ?string $lastName;

    /** @var list<string> */
    public array $queueIds;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->firstName = $this->nullableString($data['first_name'] ?? null);
        $this->lastName = $this->nullableString($data['last_name'] ?? null);
        $this->queueIds = $this->stringList($data['queues'] ?? []);
    }
}
