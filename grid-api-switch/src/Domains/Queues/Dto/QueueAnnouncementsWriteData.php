<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Queues\Dto;

use GridPbx\Switch\Shared\Support\SafeSwitchDocumentFields;
use InvalidArgumentException;

final readonly class QueueAnnouncementsWriteData
{
    public function __construct(
        public int $interval = 30,
        public bool $positionAnnouncementsEnabled = false,
        public bool $waitTimeAnnouncementsEnabled = false,
        public ?string $inTheQueueMediaId = null,
        public ?string $increaseInCallVolumeMediaId = null,
        public ?string $estimatedWaitTimeMediaId = null,
        public ?string $positionMediaId = null,
    ) {
        if ($this->interval < 15 || $this->interval > 86400) {
            throw new InvalidArgumentException('Switch queue announcement interval must be between 15 and 86400 seconds.');
        }

        $media = $this->media();

        if ($media !== [] && count($media) !== 4) {
            throw new InvalidArgumentException('Switch queue custom announcement media must provide all four prompts.');
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
                'interval', 'position_announcements_enabled',
                'wait_time_announcements_enabled', 'media',
            ]),
        ));
        $data = array_merge($preserved, [
            'interval' => $this->interval,
            'position_announcements_enabled' => $this->positionAnnouncementsEnabled,
            'wait_time_announcements_enabled' => $this->waitTimeAnnouncementsEnabled,
        ]);
        $media = $this->media();

        if ($media !== []) {
            $preservedMedia = is_array($preservedOptions['media'] ?? null)
                ? SafeSwitchDocumentFields::from(array_diff_key(
                    $preservedOptions['media'],
                    array_flip([
                        'in_the_queue', 'increase_in_call_volume',
                        'the_estimated_wait_time_is', 'you_are_at_position',
                    ]),
                ))
                : [];
            $data['media'] = array_merge($preservedMedia, $media);
        }

        return $data;
    }

    /** @return array<string, string> */
    private function media(): array
    {
        return array_filter([
            'in_the_queue' => $this->inTheQueueMediaId,
            'increase_in_call_volume' => $this->increaseInCallVolumeMediaId,
            'the_estimated_wait_time_is' => $this->estimatedWaitTimeMediaId,
            'you_are_at_position' => $this->positionMediaId,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
