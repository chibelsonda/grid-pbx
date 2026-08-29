<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Metaflows;

use stdClass;

final readonly class UserMetaflowsData
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
        $preserved = $this->preservedOptions;

        foreach (['numbers', 'patterns'] as $map) {
            if (($preserved[$map] ?? null) === []) {
                $preserved[$map] = new stdClass;
            }
        }

        $data = array_merge($preserved, array_filter([
            'binding_digit' => $this->bindingDigit,
            'digit_timeout' => $this->digitTimeout,
            'listen_on' => $this->listenOn,
        ], static fn (mixed $value): bool => $value !== null));

        return $data === [] ? new stdClass : $data;
    }
}
