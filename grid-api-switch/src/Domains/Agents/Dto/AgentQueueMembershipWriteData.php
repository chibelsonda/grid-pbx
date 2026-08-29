<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Agents\Dto;

use InvalidArgumentException;

final readonly class AgentQueueMembershipWriteData
{
    public function __construct(public string $action, public string $queueId)
    {
        if (! in_array($this->action, ['login', 'logout'], true)) {
            throw new InvalidArgumentException('Switch agent queue action is invalid.');
        }

        if ($this->queueId === '') {
            throw new InvalidArgumentException('Switch queue identifier is required.');
        }
    }

    /** @return array{action: string, queue_id: string} */
    public function toSwitchData(): array
    {
        return ['action' => $this->action, 'queue_id' => $this->queueId];
    }
}
