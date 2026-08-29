<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\CallerId;

final readonly class UserCallerIdScopeData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public ?string $name = null,
        public ?string $number = null,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_merge($this->preservedOptions, [
            'name' => $this->name,
            'number' => $this->number,
        ]);
    }
}
