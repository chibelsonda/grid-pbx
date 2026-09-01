<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Conferences\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class ConferenceParticipantSnapshot
{
    public string $id;

    public ?string $displayName;

    public ?string $number;

    public bool $isModerator;

    public bool $canSpeak;

    public bool $canHear;

    public int $durationSeconds;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $id = $data['participant_id'] ?? null;
        $id = is_int($id) ? (string) $id : $id;

        if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1) {
            throw new InvalidSwitchPayloadException('Switch conference participant must contain a positive participant id.');
        }

        $variables = is_array($data['conference_channel_vars'] ?? null)
            ? $data['conference_channel_vars']
            : [];

        $this->id = $id;
        $this->displayName = $this->optionalString($data['caller_id_name'] ?? null);
        $this->number = $this->optionalString($data['caller_id_number'] ?? null);
        $this->isModerator = (bool) ($variables['is_moderator'] ?? false);
        $this->canSpeak = (bool) ($variables['speak'] ?? true);
        $this->canHear = (bool) ($variables['hear'] ?? true);
        $this->durationSeconds = max(0, (int) ($data['duration'] ?? 0));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->displayName,
            'number' => $this->number,
            'is_moderator' => $this->isModerator,
            'can_speak' => $this->canSpeak,
            'can_hear' => $this->canHear,
            'duration_seconds' => $this->durationSeconds,
        ];
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
