<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Faxes\Dto;

use InvalidArgumentException;

final readonly class FaxBoxWriteData
{
    /** @param list<string> $smtpPermissionList
     * @param list<string> $inboundNotificationEmails
     * @param list<string> $outboundNotificationEmails
     */
    public function __construct(
        public string $name,
        public ?string $ownerId = null,
        public ?string $callerId = null,
        public ?string $callerName = null,
        public ?string $faxHeader = null,
        public ?string $faxIdentity = null,
        public ?string $timezone = null,
        public int $retries = 1,
        public bool $t38Enabled = false,
        public ?string $customSmtpEmailAddress = null,
        public array $smtpPermissionList = [],
        public array $inboundNotificationEmails = [],
        public array $outboundNotificationEmails = [],
    ) {
        if (trim($this->name) === '') throw new InvalidArgumentException('Switch fax box name is required.');
        if ($this->retries < 0 || $this->retries > 4) throw new InvalidArgumentException('Switch fax retry count must be between zero and four.');
        foreach ([...$this->inboundNotificationEmails, ...$this->outboundNotificationEmails] as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('Switch fax notification recipients must be valid email addresses.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        return array_filter([
            'name' => trim($this->name), 'owner_id' => $this->ownerId, 'caller_id' => $this->callerId,
            'caller_name' => $this->callerName, 'fax_header' => $this->faxHeader,
            'fax_identity' => $this->faxIdentity, 'fax_timezone' => $this->timezone,
            'retries' => $this->retries, 'media' => ['fax_option' => $this->t38Enabled],
            'custom_smtp_email_address' => $this->customSmtpEmailAddress,
            'smtp_permission_list' => array_values(array_unique($this->smtpPermissionList)),
            'notifications' => [
                'inbound' => ['email' => ['send_to' => array_values(array_unique($this->inboundNotificationEmails))]],
                'outbound' => ['email' => ['send_to' => array_values(array_unique($this->outboundNotificationEmails))]],
            ],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
