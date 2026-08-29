<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountDialPlanRuleData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public string $pattern,
        public ?string $description = null,
        public ?string $prefix = null,
        public ?string $suffix = null,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed> */
    public function options(): array
    {
        return array_merge($this->preservedOptions, array_filter([
            'description' => $this->description,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }
}
