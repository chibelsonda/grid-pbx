<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Media;

use stdClass;

final readonly class UserMusicOnHoldData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public ?string $mediaId,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed>|stdClass */
    public function toSwitchData(): array|stdClass
    {
        if ($this->mediaId === null && $this->preservedOptions === []) {
            return new stdClass;
        }

        return array_merge(
            $this->preservedOptions,
            $this->mediaId === null ? [] : ['media_id' => $this->mediaId],
        );
    }
}
