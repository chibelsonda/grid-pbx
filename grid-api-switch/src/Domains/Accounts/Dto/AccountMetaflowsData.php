<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

use stdClass;

final readonly class AccountMetaflowsData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public ?string $bindingDigit = null,
        public ?int $digitTimeout = null,
        public ?string $listenOn = null,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed>|stdClass */
    public function toSwitchData(): array|stdClass
    {
        $data = array_merge($this->preservedOptions, array_filter([
            'binding_digit' => $this->bindingDigit,
            'digit_timeout' => $this->digitTimeout,
            'listen_on' => $this->listenOn,
        ], static fn (mixed $value): bool => $value !== null));

        return $data === [] ? new stdClass : $data;
    }
}
