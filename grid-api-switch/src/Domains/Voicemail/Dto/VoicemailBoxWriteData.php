<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Voicemail\Dto;

use InvalidArgumentException;

final readonly class VoicemailBoxWriteData
{
    /** @param list<string> $notificationEmails */
    public function __construct(
        public string $name,
        public string $mailbox,
        public ?string $ownerId = null,
        public ?string $timezone = null,
        public array $notificationEmails = [],
        public bool $transcribe = false,
        public bool $requirePin = false,
        private ?string $pin = null,
        public bool $preservePin = false,
        public ?VoicemailBoxAdvancedData $advanced = null,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch voicemail box name is required.');
        }

        if (trim($this->mailbox) === '') {
            throw new InvalidArgumentException('Switch voicemail mailbox number is required.');
        }

        if ($this->pin !== null && $this->preservePin) {
            throw new InvalidArgumentException('A voicemail PIN cannot be set and preserved in the same request.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = [
            'name' => $this->name,
            'mailbox' => $this->mailbox,
        ];

        if ($this->ownerId !== null) {
            $data['owner_id'] = $this->ownerId;
        }

        $data = [
            ...$data,
            'notify_email_addresses' => $this->notificationEmails,
            'transcribe' => $this->transcribe,
            'require_pin' => $this->requirePin,
        ];

        if ($this->timezone !== null) {
            $data['timezone'] = $this->timezone;
        }

        if ($this->pin !== null) {
            $data['pin'] = $this->pin;
        }

        return array_replace($data, $this->advanced?->toSwitchData() ?? []);
    }
}
