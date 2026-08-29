<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceDialPlanData
{
    /**
     * @param  list<string>  $system
     * @param  list<array{pattern: string, description?: string|null, prefix?: string|null, suffix?: string|null}>  $rules
     */
    public function __construct(
        public array $system = [],
        public array $rules = [],
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = ['system' => $this->system];

        foreach ($this->rules as $rule) {
            $pattern = $rule['pattern'];
            $data[$pattern] = array_filter([
                'description' => $rule['description'] ?? null,
                'prefix' => $rule['prefix'] ?? null,
                'suffix' => $rule['suffix'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return $data;
    }
}
