<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Media\Dto;

use InvalidArgumentException;

final readonly class MediaWriteData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public string $mediaSource = 'upload',
        public bool $streamable = true,
        public ?string $language = null,
        public ?string $contentType = null,
        public ?int $contentLength = null,
        public ?string $promptId = null,
        public ?string $sourceId = null,
        public ?string $sourceType = null,
        public ?MediaTtsWriteData $tts = null,
        public array $preservedOptions = [],
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch media name is required.');
        }

        if ($this->contentLength !== null && $this->contentLength < 1) {
            throw new InvalidArgumentException('Switch media content length must be greater than zero.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_merge($this->preservedOptions, array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'media_source' => $this->mediaSource,
            'streamable' => $this->streamable,
            'language' => $this->language,
            'content_type' => $this->contentType,
            'content_length' => $this->contentLength,
            'prompt_id' => $this->promptId,
            'source_id' => $this->sourceId,
            'source_type' => $this->sourceType,
            'tts' => $this->tts?->toSwitchData(),
        ], static fn (mixed $value): bool => $value !== null));
    }
}
