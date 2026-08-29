<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

use stdClass;

final readonly class DeviceFormattersData
{
    /** @param list<DeviceFormatterRuleData> $rules */
    public function __construct(public array $rules = []) {}

    /** @return array<string, list<array<string, bool|string>>>|stdClass */
    public function toSwitchData(): array|stdClass
    {
        $formatters = [];

        foreach ($this->rules as $rule) {
            $formatters[$rule->field][] = $rule->options();
        }

        return $formatters === [] ? new stdClass : $formatters;
    }
}
