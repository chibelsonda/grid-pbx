<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Faxes;

use GridPbx\Switch\Dto\Common\EntitySnapshot;
use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

final readonly class FaxBoxSnapshot extends EntitySnapshot
{
    public string $name;
    public ?string $ownerId;
    public ?string $callerId;
    public ?string $callerName;
    public ?string $faxHeader;
    public ?string $faxIdentity;
    public ?string $timezone;
    public int $retries;
    public bool $t38Enabled;
    public ?string $smtpEmailAddress;
    public ?string $customSmtpEmailAddress;
    /** @var list<string> */ public array $smtpPermissionList;
    /** @var list<string> */ public array $inboundNotificationEmails;
    /** @var list<string> */ public array $outboundNotificationEmails;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $name = $data['name'] ?? null;
        if (! is_string($name) || trim($name) === '') throw new InvalidSwitchPayloadException('Switch fax box response is missing its name.');
        $media = is_array($data['media'] ?? null) ? $data['media'] : [];
        $notifications = is_array($data['notifications'] ?? null) ? $data['notifications'] : [];
        $this->name = $name;
        $this->ownerId = $this->nullableString($data['owner_id'] ?? null);
        $this->callerId = $this->nullableString($data['caller_id'] ?? null);
        $this->callerName = $this->nullableString($data['caller_name'] ?? null);
        $this->faxHeader = $this->nullableString($data['fax_header'] ?? null);
        $this->faxIdentity = $this->nullableString($data['fax_identity'] ?? null);
        $this->timezone = $this->nullableString($data['fax_timezone'] ?? null);
        $this->retries = max(0, min(4, (int) ($data['retries'] ?? 1)));
        $this->t38Enabled = (bool) ($media['fax_option'] ?? false);
        $this->smtpEmailAddress = $this->nullableString($data['smtp_email_address'] ?? null);
        $this->customSmtpEmailAddress = $this->nullableString($data['custom_smtp_email_address'] ?? null);
        $this->smtpPermissionList = $this->stringList($data['smtp_permission_list'] ?? null);
        $this->inboundNotificationEmails = $this->recipients($notifications, 'inbound');
        $this->outboundNotificationEmails = $this->recipients($notifications, 'outbound');
    }

    /** @param array<string, mixed> $notifications
     * @return list<string>
     */
    private function recipients(array $notifications, string $direction): array
    {
        $group = is_array($notifications[$direction] ?? null) ? $notifications[$direction] : [];
        $email = is_array($group['email'] ?? null) ? $group['email'] : [];
        $sendTo = $email['send_to'] ?? null;
        return is_string($sendTo) && $sendTo !== '' ? [$sendTo] : $this->stringList($sendTo);
    }
}
