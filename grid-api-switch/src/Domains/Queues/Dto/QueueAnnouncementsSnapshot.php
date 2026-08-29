<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues\Dto;

final readonly class QueueAnnouncementsSnapshot
{
    /** @param array<string, mixed> $data */
    public function __construct(public array $data) {}

    public function interval(): int
    {
        return max(15, min(86400, (int) ($this->data['interval'] ?? 30)));
    }

    public function positionAnnouncementsEnabled(): bool
    {
        return (bool) ($this->data['position_announcements_enabled'] ?? false);
    }

    public function waitTimeAnnouncementsEnabled(): bool
    {
        return (bool) ($this->data['wait_time_announcements_enabled'] ?? false);
    }

    public function mediaId(string $key): ?string
    {
        $media = is_array($this->data['media'] ?? null) ? $this->data['media'] : [];
        $value = $media[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
