<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

final readonly class AccountFormatterRuleData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public string $field,
        public ?string $direction = null,
        public ?bool $matchInviteFormat = null,
        public ?string $prefix = null,
        public ?string $regex = null,
        public ?bool $strip = null,
        public ?string $suffix = null,
        public ?string $value = null,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed> */
    public function options(): array
    {
        return array_merge($this->preservedOptions, array_filter([
            'direction' => $this->direction,
            'match_invite_format' => $this->matchInviteFormat,
            'prefix' => $this->prefix,
            'regex' => $this->regex,
            'strip' => $this->strip,
            'suffix' => $this->suffix,
            'value' => $this->value,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
