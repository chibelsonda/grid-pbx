<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto;

final readonly class VoicemailBoxSnapshot extends EntitySnapshot
{
    public ?string $ownerId;

    public ?string $name;

    public ?string $mailbox;

    public ?bool $isSetup;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->ownerId = $this->nullableString($data['owner_id'] ?? null);
        $this->name = $this->nullableString($data['name'] ?? null);
        $this->mailbox = $this->nullableString($data['mailbox'] ?? null);
        $this->isSetup = array_key_exists('is_setup', $data) ? (bool) $data['is_setup'] : null;
    }
}
