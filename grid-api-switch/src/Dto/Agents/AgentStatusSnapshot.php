<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Agents;

final readonly class AgentStatusSnapshot
{
    public ?string $status;

    public ?int $timestamp;

    /** @param array<string, mixed> $data */
    public function __construct(public array $data)
    {
        $this->status = is_string($data['status'] ?? null) && $data['status'] !== '' ? $data['status'] : null;
        $this->timestamp = is_int($data['timestamp'] ?? null) ? $data['timestamp'] : null;
    }
}
