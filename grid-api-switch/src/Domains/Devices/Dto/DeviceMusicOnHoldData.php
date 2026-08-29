<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Devices\Dto;

use stdClass;

final readonly class DeviceMusicOnHoldData
{
    public function __construct(public ?string $mediaId = null) {}

    /** @return array{media_id: string}|stdClass */
    public function toSwitchData(): array|stdClass
    {
        return $this->mediaId === null ? new stdClass() : ['media_id' => $this->mediaId];
    }
}
