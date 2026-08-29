<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Media\Dto;

use InvalidArgumentException;

final readonly class MediaTtsWriteData
{
    public function __construct(
        public string $text,
        public string $voice,
    ) {
        if (trim($this->text) === '') {
            throw new InvalidArgumentException('Switch media text-to-speech text is required.');
        }

        if (trim($this->voice) === '') {
            throw new InvalidArgumentException('Switch media text-to-speech voice is required.');
        }
    }

    /** @return array{text: string, voice: string} */
    public function toSwitchData(): array
    {
        return [
            'text' => $this->text,
            'voice' => $this->voice,
        ];
    }
}
