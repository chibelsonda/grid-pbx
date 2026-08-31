<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues\Dto;

use GridPbx\Switch\Shared\Support\SafeSwitchDocumentFields;
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
        public ?string $announceMediaId = null,
        public ?int $maxPriority = null,
        public ?QueueAnnouncementsWriteData $announcements = null,
        public ?string $cdrUrl = null,
        public ?string $recordingUrl = null,
    ) {
        if (trim($this->name) === '' || mb_strlen($this->name) > 128) {
            throw new InvalidArgumentException('Switch queue name must contain between 1 and 128 characters.');
        }

        if (! in_array($this->strategy, ['round_robin', 'most_idle'], true)) {
            throw new InvalidArgumentException('Switch queue strategy is invalid.');
        }

        if ($this->agentRingTimeout < 1 || $this->agentRingTimeout > 300
            || $this->agentWrapupTime < 0 || $this->agentWrapupTime > 3600
            || $this->connectionTimeout < 0 || $this->connectionTimeout > 86400
            || $this->maxQueueSize < 0 || $this->maxQueueSize > 10000
            || $this->ringSimultaneously < 1 || $this->ringSimultaneously > 100) {
            throw new InvalidArgumentException('Switch queue numeric settings are invalid.');
        }

        if (! in_array($this->callerExitKey, ['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#'], true)) {
            throw new InvalidArgumentException('Switch queue caller exit key is invalid.');
        }

        if ($this->maxPriority !== null && ($this->maxPriority < 0 || $this->maxPriority > 255)) {
            throw new InvalidArgumentException('Switch queue maximum priority must be between 0 and 255.');
        }
    }

    /**
     * @param  array<string, mixed>  $preservedOptions
     * @return array<string, mixed>
     */
    public function toSwitchData(array $preservedOptions = []): array
    {
        $preserved = SafeSwitchDocumentFields::from(array_diff_key(
            $preservedOptions,
            array_flip([
                'id', 'name', 'strategy', 'agent_ring_timeout', 'agent_wrapup_time',
                'connection_timeout', 'max_queue_size', 'ring_simultaneously',
                'enter_when_empty', 'record_caller', 'caller_exit_key', 'moh',
                'announce', 'announcements', 'agents',
            ]),
        ));
        $data = array_merge($preserved, [
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
        ]);

        if ($this->musicOnHoldMediaId !== null) {
            $data['moh'] = $this->musicOnHoldMediaId;
        }

        if ($this->announceMediaId !== null) {
            $data['announce'] = $this->announceMediaId;
        }

        if ($this->maxPriority !== null) {
            $data['max_priority'] = $this->maxPriority;
        }

        if ($this->announcements !== null) {
            $data['announcements'] = $this->announcements->toSwitchData(
                is_array($preservedOptions['announcements'] ?? null)
                    ? $preservedOptions['announcements']
                    : [],
            );
        }

        if ($this->cdrUrl !== null) {
            $data['cdr_url'] = $this->cdrUrl;
        }

        if ($this->recordingUrl !== null) {
            $data['recording_url'] = $this->recordingUrl;
        }

        return $data;
    }
}
