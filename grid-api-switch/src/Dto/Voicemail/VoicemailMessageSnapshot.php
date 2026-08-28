<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Voicemail;

use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

final readonly class VoicemailMessageSnapshot
{
    public string $mediaId;

    public ?string $folder;

    public ?string $callerIdName;

    public ?string $callerIdNumber;

    public ?int $length;

    public ?int $timestamp;

    public ?string $transcriptionResult;

    public ?string $transcriptionText;

    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
        $mediaId = $data['media_id'] ?? null;

        if (! is_string($mediaId) || $mediaId === '') {
            throw new InvalidSwitchPayloadException('Switch voicemail message must contain a non-empty media_id.');
        }

        $this->mediaId = $mediaId;
        $this->folder = $this->nullableString($data['folder'] ?? null);
        $this->callerIdName = $this->nullableString($data['caller_id_name'] ?? null);
        $this->callerIdNumber = $this->nullableString($data['caller_id_number'] ?? null);
        $this->length = is_int($data['length'] ?? null) ? $data['length'] : null;
        $this->timestamp = is_int($data['timestamp'] ?? null) ? $data['timestamp'] : null;
        $transcription = is_array($data['transcription'] ?? null) ? $data['transcription'] : [];
        $this->transcriptionResult = $this->nullableString($transcription['result'] ?? null);
        $this->transcriptionText = $this->nullableString($transcription['text'] ?? null);
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
