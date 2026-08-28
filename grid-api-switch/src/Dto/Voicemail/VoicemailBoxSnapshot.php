<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Voicemail;

use GridPbx\Switch\Dto\Common\EntitySnapshot;

final readonly class VoicemailBoxSnapshot extends EntitySnapshot
{
    public ?string $ownerId;

    public ?string $name;

    public ?string $mailbox;

    public ?bool $isSetup;

    public ?string $timezone;

    /** @var list<string> */
    public array $notificationEmails;

    public bool $transcribe;

    public bool $requirePin;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->ownerId = $this->nullableString($data['owner_id'] ?? null);
        $this->name = $this->nullableString($data['name'] ?? null);
        $this->mailbox = $this->nullableString($data['mailbox'] ?? null);
        $this->isSetup = array_key_exists('is_setup', $data) ? (bool) $data['is_setup'] : null;
        $this->timezone = $this->nullableString($data['timezone'] ?? null);
        $this->notificationEmails = array_values(array_filter(
            is_array($data['notify_email_addresses'] ?? null) ? $data['notify_email_addresses'] : [],
            static fn (mixed $email): bool => is_string($email) && $email !== '',
        ));
        $this->transcribe = (bool) ($data['transcribe'] ?? false);
        $this->requirePin = (bool) ($data['require_pin'] ?? false);
    }
}
