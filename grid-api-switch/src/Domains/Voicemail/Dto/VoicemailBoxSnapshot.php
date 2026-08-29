<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Voicemail\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;

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

    public bool $checkIfOwner;

    public bool $deleteAfterNotify;

    public bool $includeMessageOnNotify;

    public bool $includeTranscriptionOnNotify;

    public string $mediaExtension;

    public bool $notConfigurable;

    public bool $oldestMessageFirst;

    public bool $saveAfterNotify;

    public bool $skipEnvelope;

    public bool $skipGreeting;

    public bool $skipInstructions;

    public bool $fastForwardRewindEnabled;

    public int $seekDurationMilliseconds;

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
        $this->checkIfOwner = (bool) ($data['check_if_owner'] ?? true);
        $this->deleteAfterNotify = (bool) ($data['delete_after_notify'] ?? false);
        $this->includeMessageOnNotify = (bool) ($data['include_message_on_notify'] ?? true);
        $this->includeTranscriptionOnNotify = (bool) ($data['include_transcription_on_notify'] ?? true);
        $this->mediaExtension = in_array($data['media_extension'] ?? null, ['mp3', 'mp4', 'wav'], true)
            ? $data['media_extension']
            : 'mp3';
        $this->notConfigurable = (bool) ($data['not_configurable'] ?? false);
        $this->oldestMessageFirst = (bool) ($data['oldest_message_first'] ?? false);
        $this->saveAfterNotify = (bool) ($data['save_after_notify'] ?? false);
        $this->skipEnvelope = (bool) ($data['skip_envelope'] ?? false);
        $this->skipGreeting = (bool) ($data['skip_greeting'] ?? false);
        $this->skipInstructions = (bool) ($data['skip_instructions'] ?? false);
        $this->fastForwardRewindEnabled = (bool) ($data['is_voicemail_ff_rw_enabled'] ?? false);
        $this->seekDurationMilliseconds = is_int($data['seek_duration_ms'] ?? null)
            ? $data['seek_duration_ms']
            : 10000;
    }
}
