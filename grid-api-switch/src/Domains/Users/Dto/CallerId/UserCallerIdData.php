<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\CallerId;

final readonly class UserCallerIdData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public UserCallerIdScopeData $internal,
        public UserCallerIdScopeData $external,
        public UserCallerIdScopeData $emergency,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_merge($this->preservedOptions, [
            'internal' => $this->internal->toSwitchData(),
            'external' => $this->external->toSwitchData(),
            'emergency' => $this->emergency->toSwitchData(),
        ]);
    }
}
