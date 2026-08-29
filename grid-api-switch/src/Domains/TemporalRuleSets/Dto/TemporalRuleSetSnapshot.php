<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\TemporalRuleSets\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class TemporalRuleSetSnapshot extends EntitySnapshot
{
    public string $name;

    /** @var list<string> */
    public array $temporalRuleIds;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $name = $data['name'] ?? null;
        if (! is_string($name) || trim($name) === '') {
            throw new InvalidSwitchPayloadException('Switch temporal rule set response is missing its name.');
        }
        $rules = $data['temporal_rules'] ?? [];
        if (! is_array($rules) || array_filter($rules, static fn (mixed $id): bool => ! is_string($id) || $id === '')) {
            throw new InvalidSwitchPayloadException('Switch temporal rule set response has invalid rule identifiers.');
        }
        $this->name = $name;
        $this->temporalRuleIds = array_values($rules);
    }
}
