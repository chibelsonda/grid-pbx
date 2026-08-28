<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Media;

use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

final readonly class MediaSnapshot
{
    public string $id;

    public ?string $name;

    public ?string $description;

    public ?string $language;

    public ?string $contentType;

    public ?int $contentLength;

    public ?string $mediaSource;

    public ?string $promptId;

    public bool $streamable;

    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
        $id = $data['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new InvalidSwitchPayloadException('Switch media must contain a non-empty id.');
        }

        $this->id = $id;
        $this->name = $this->nullableString($data['name'] ?? null);
        $this->description = $this->nullableString($data['description'] ?? null);
        $this->language = $this->nullableString($data['language'] ?? null);
        $this->contentType = $this->nullableString($data['content_type'] ?? null);
        $this->contentLength = is_int($data['content_length'] ?? null) ? $data['content_length'] : null;
        $this->mediaSource = $this->nullableString($data['media_source'] ?? null);
        $this->promptId = $this->nullableString($data['prompt_id'] ?? null);
        $this->streamable = (bool) ($data['streamable'] ?? true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
