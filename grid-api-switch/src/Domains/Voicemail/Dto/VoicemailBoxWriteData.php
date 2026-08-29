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
        public ?VoicemailBoxAdvancedData $advanced = null,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Switch voicemail box name is required.');
        }

        if (trim($this->mailbox) === '') {
            throw new InvalidArgumentException('Switch voicemail mailbox number is required.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = [
            'name' => $this->name,
            'mailbox' => $this->mailbox,
            'owner_id' => $this->ownerId,
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
