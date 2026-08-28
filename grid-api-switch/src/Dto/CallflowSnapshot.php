<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto;

final readonly class CallflowSnapshot extends EntitySnapshot
{
    public ?string $name;

    /** @var list<string> */
    public array $numbers;

    /** @var list<string> */
    public array $modules;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->name = $this->nullableString($data['name'] ?? null);
        $this->numbers = $this->stringList($data['numbers'] ?? null);
        $this->modules = $this->stringList($data['modules'] ?? null);
    }
}
