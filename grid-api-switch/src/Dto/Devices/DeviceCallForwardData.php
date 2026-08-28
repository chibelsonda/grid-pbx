<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Devices;

final readonly class DeviceCallForwardData
{
    public function __construct(
        public ?bool $enabled = null,
        public ?string $number = null,
        public ?bool $directCallsOnly = null,
        public ?bool $failover = null,
        public ?bool $ignoreEarlyMedia = null,
        public ?bool $keepCallerId = null,
        public ?bool $requireKeypress = null,
        public ?bool $substitute = null,
    ) {}

    /** @return array<string, bool|string> */
    public function toSwitchData(): array
    {
        return array_filter([
            'enabled' => $this->enabled,
            'number' => $this->number,
            'direct_calls_only' => $this->directCallsOnly,
            'failover' => $this->failover,
            'ignore_early_media' => $this->ignoreEarlyMedia,
            'keep_caller_id' => $this->keepCallerId,
            'require_keypress' => $this->requireKeypress,
            'substitute' => $this->substitute,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
