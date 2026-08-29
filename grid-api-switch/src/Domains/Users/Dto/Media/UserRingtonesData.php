<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Media;

use stdClass;

final readonly class UserRingtonesData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public ?string $internal,
        public ?string $external,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed>|stdClass */
    public function toSwitchData(): array|stdClass
    {
        $data = array_merge($this->preservedOptions, array_filter([
            'internal' => $this->internal,
            'external' => $this->external,
        ], static fn (?string $value): bool => $value !== null));

        return $data === [] ? new stdClass : $data;
    }
}
