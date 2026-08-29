<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

final readonly class DeviceFormatterRuleData
{
    public function __construct(
        public string $field,
        public ?string $direction = null,
        public ?bool $matchInviteFormat = null,
        public ?string $prefix = null,
        public ?string $regex = null,
        public ?bool $strip = null,
        public ?string $suffix = null,
        public ?string $value = null,
    ) {}

    /** @return array<string, bool|string> */
    public function options(): array
    {
        return array_filter([
            'direction' => $this->direction,
            'match_invite_format' => $this->matchInviteFormat,
            'prefix' => $this->prefix,
            'regex' => $this->regex,
            'strip' => $this->strip,
            'suffix' => $this->suffix,
            'value' => $this->value,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
