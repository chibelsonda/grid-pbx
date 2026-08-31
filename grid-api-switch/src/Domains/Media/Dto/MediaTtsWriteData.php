<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Media\Dto;

use InvalidArgumentException;

final readonly class MediaTtsWriteData
{
    /** @param array<string, mixed> $preservedOptions */
    public function __construct(
        public string $text,
        public string $voice,
        public array $preservedOptions = [],
    ) {
        if (trim($this->text) === '') {
            throw new InvalidArgumentException('Switch media text-to-speech text is required.');
        }

        if (trim($this->voice) === '') {
            throw new InvalidArgumentException('Switch media text-to-speech voice is required.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_merge($this->preservedOptions, [
            'text' => $this->text,
            'voice' => $this->voice,
        ]);
    }
}
