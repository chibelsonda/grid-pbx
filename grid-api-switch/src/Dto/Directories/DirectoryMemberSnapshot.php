<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Directories;

use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

final readonly class DirectoryMemberSnapshot
{
    public string $userId;

    public string $callflowId;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $userId = $data['user_id'] ?? null;
        $callflowId = $data['callflow_id'] ?? null;

        if (! is_string($userId) || $userId === '' || ! is_string($callflowId) || $callflowId === '') {
            throw new InvalidSwitchPayloadException('Switch directory member must contain user and callflow identifiers.');
        }

        $this->userId = $userId;
        $this->callflowId = $callflowId;
    }

    /** @return array{user_id: string, callflow_id: string} */
    public function toArray(): array
    {
        return ['user_id' => $this->userId, 'callflow_id' => $this->callflowId];
    }
}
