<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues\Dto;

use InvalidArgumentException;

final readonly class QueueWriteData
{
    public function __construct(
        public string $name,
        public string $strategy = 'round_robin',
        public int $agentRingTimeout = 15,
        public int $agentWrapupTime = 0,
        public int $connectionTimeout = 3600,
        public int $maxQueueSize = 0,
        public int $ringSimultaneously = 1,
        public bool $enterWhenEmpty = true,
        public bool $recordCaller = false,
        public string $callerExitKey = '#',
        public ?string $musicOnHoldMediaId = null,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch queue name is required.');
        }

        if (! in_array($this->strategy, ['round_robin', 'most_idle'], true)) {
            throw new InvalidArgumentException('Switch queue strategy is invalid.');
        }

        if ($this->agentRingTimeout < 1 || $this->agentWrapupTime < 0 || $this->connectionTimeout < 0 || $this->maxQueueSize < 0 || $this->ringSimultaneously < 1) {
            throw new InvalidArgumentException('Switch queue numeric settings are invalid.');
        }

        if (! in_array($this->callerExitKey, ['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#'], true)) {
            throw new InvalidArgumentException('Switch queue caller exit key is invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = [
            'name' => $this->name,
            'strategy' => $this->strategy,
            'agent_ring_timeout' => $this->agentRingTimeout,
            'agent_wrapup_time' => $this->agentWrapupTime,
            'connection_timeout' => $this->connectionTimeout,
            'max_queue_size' => $this->maxQueueSize,
            'ring_simultaneously' => $this->ringSimultaneously,
            'enter_when_empty' => $this->enterWhenEmpty,
            'record_caller' => $this->recordCaller,
            'caller_exit_key' => $this->callerExitKey,
        ];

        if ($this->musicOnHoldMediaId !== null) {
            $data['moh'] = $this->musicOnHoldMediaId;
        }

        return $data;
    }
}
