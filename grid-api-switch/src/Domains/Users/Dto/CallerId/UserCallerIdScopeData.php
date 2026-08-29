<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\CallerId;

use stdClass;

final readonly class UserCallerIdScopeData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public ?string $name = null,
        public ?string $number = null,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed>|stdClass */
    public function toSwitchData(): array|stdClass
    {
        $data = array_merge($this->preservedOptions, array_filter([
            'name' => $this->name,
            'number' => $this->number,
        ], static fn (?string $value): bool => $value !== null));

        return $data === [] ? new stdClass : $data;
    }
}
