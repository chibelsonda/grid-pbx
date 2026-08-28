<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Agents;

use InvalidArgumentException;

final readonly class AgentStatusWriteData
{
    public function __construct(public string $status, public ?int $pauseTimeout = null)
    {
        if (! in_array($this->status, ['login', 'logout', 'pause', 'resume', 'end_wrapup'], true)) {
            throw new InvalidArgumentException('Switch agent status is invalid.');
        }

        if ($this->pauseTimeout !== null && $this->pauseTimeout < 0) {
            throw new InvalidArgumentException('Switch agent pause timeout cannot be negative.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_filter([
            'status' => $this->status,
            'timeout' => $this->pauseTimeout,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
