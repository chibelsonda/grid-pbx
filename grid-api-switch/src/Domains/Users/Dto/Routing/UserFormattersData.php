<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Routing;

use stdClass;

final readonly class UserFormattersData
{
    /** @param list<UserFormatterRuleData> $rules */
    public function __construct(public array $rules = []) {}

    /** @return array<string, list<array<string, mixed>>>|stdClass */
    public function toSwitchData(): array|stdClass
    {
        $formatters = [];

        foreach ($this->rules as $rule) {
            $formatters[$rule->field][] = $rule->options();
        }

        return $formatters === [] ? new stdClass : $formatters;
    }
}
