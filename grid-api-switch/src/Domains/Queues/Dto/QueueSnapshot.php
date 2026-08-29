<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class QueueSnapshot extends EntitySnapshot
{
    public string $name;

    public string $strategy;

    public int $agentRingTimeout;

    public int $agentWrapupTime;

    public int $connectionTimeout;

    public int $maxQueueSize;

    public int $ringSimultaneously;

    public bool $enterWhenEmpty;

    public bool $recordCaller;

    public string $callerExitKey;

    public ?string $musicOnHoldMediaId;

    public ?string $announceMediaId;

    public ?int $maxPriority;

    public ?QueueAnnouncementsSnapshot $announcements;

    public ?string $cdrUrl;

    public ?string $recordingUrl;

    /** @var list<string> */
    public array $agentIds;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $name = $data['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw new InvalidSwitchPayloadException('Switch queue response is missing its name.');
        }

        $this->name = $name;
        $this->strategy = in_array($data['strategy'] ?? null, ['round_robin', 'most_idle'], true)
            ? $data['strategy']
            : 'round_robin';
        $this->agentRingTimeout = max(1, (int) ($data['agent_ring_timeout'] ?? 15));
        $this->agentWrapupTime = max(0, (int) ($data['agent_wrapup_time'] ?? 0));
        $this->connectionTimeout = max(0, (int) ($data['connection_timeout'] ?? 3600));
        $this->maxQueueSize = max(0, (int) ($data['max_queue_size'] ?? 0));
        $this->ringSimultaneously = max(1, (int) ($data['ring_simultaneously'] ?? 1));
        $this->enterWhenEmpty = (bool) ($data['enter_when_empty'] ?? true);
        $this->recordCaller = (bool) ($data['record_caller'] ?? false);
        $this->callerExitKey = is_string($data['caller_exit_key'] ?? null) ? $data['caller_exit_key'] : '#';
        $this->musicOnHoldMediaId = $this->nullableString($data['moh'] ?? null);
        $this->announceMediaId = $this->nullableString($data['announce'] ?? null);
        $this->maxPriority = is_int($data['max_priority'] ?? null) ? $data['max_priority'] : null;
        $this->announcements = is_array($data['announcements'] ?? null)
            ? new QueueAnnouncementsSnapshot($data['announcements'])
            : null;
        $this->cdrUrl = $this->nullableString($data['cdr_url'] ?? null);
        $this->recordingUrl = $this->nullableString($data['recording_url'] ?? null);
        $this->agentIds = $this->stringList($data['agents'] ?? []);
    }
}
