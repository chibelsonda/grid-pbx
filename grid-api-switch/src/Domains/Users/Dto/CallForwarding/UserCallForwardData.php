<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\CallForwarding;

final readonly class UserCallForwardData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public bool $enabled,
        public ?string $number = null,
        public bool $directCallsOnly = false,
        public bool $failover = false,
        public bool $ignoreEarlyMedia = true,
        public bool $keepCallerId = true,
        public bool $requireKeypress = true,
        public bool $substitute = true,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_merge($this->preservedOptions, array_filter([
            'enabled' => $this->enabled,
            'number' => $this->number,
            'direct_calls_only' => $this->directCallsOnly,
            'failover' => $this->failover,
            'ignore_early_media' => $this->ignoreEarlyMedia,
            'keep_caller_id' => $this->keepCallerId,
            'require_keypress' => $this->requireKeypress,
            'substitute' => $this->substitute,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
