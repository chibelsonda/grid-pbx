<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\TemporalRuleSets\Dto;

use InvalidArgumentException;

final readonly class TemporalRuleSetWriteData
{
    /** @param list<string> $temporalRuleIds */
    public function __construct(public string $name, public array $temporalRuleIds)
    {
        if (trim($this->name) === '' || array_filter($this->temporalRuleIds, static fn (mixed $id): bool => ! is_string($id) || $id === '')) {
            throw new InvalidArgumentException('Switch temporal rule set name and rule identifiers are invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return ['name' => $this->name, 'temporal_rules' => array_values(array_unique($this->temporalRuleIds))];
    }
}
