<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Accounts;

final readonly class MusicOnHoldWriteData
{
    public function __construct(public ?string $mediaId) {}

    /** @return array{music_on_hold: array{media_id: string}} */
    public function toSwitchData(): array
    {
        return [
            'music_on_hold' => ['media_id' => $this->mediaId ?? ''],
        ];
    }
}
