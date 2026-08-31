<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\TemporalRuleSets\Dto;

use InvalidArgumentException;

final readonly class TemporalRuleSetWriteData
{
    /** @param list<string> $temporalRuleIds
     * @param  list<string>  $flags
     */
    public function __construct(public string $name, public array $temporalRuleIds, public array $flags = [])
    {
        if (trim($this->name) === '' || mb_strlen($this->name) > 128 || $this->temporalRuleIds === []
            || array_filter($this->temporalRuleIds, static fn (mixed $id): bool => ! is_string($id) || $id === '')
            || count($this->temporalRuleIds) !== count(array_unique($this->temporalRuleIds))
            || array_filter($this->flags, static fn (mixed $flag): bool => ! is_string($flag))) {
            throw new InvalidArgumentException('Switch temporal rule set name and rule identifiers are invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_filter([
            'name' => $this->name,
            'temporal_rules' => array_values(array_unique($this->temporalRuleIds)),
            'flags' => $this->flags === [] ? null : array_values(array_unique($this->flags)),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
