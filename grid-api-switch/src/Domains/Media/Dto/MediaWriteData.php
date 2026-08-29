<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Media\Dto;

use InvalidArgumentException;

final readonly class MediaWriteData
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public string $mediaSource = 'upload',
        public bool $streamable = true,
        public ?string $language = null,
        public ?string $sourceId = null,
        public ?string $sourceType = null,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch media name is required.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'media_source' => $this->mediaSource,
            'streamable' => $this->streamable,
            'language' => $this->language,
            'source_id' => $this->sourceId,
            'source_type' => $this->sourceType,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
