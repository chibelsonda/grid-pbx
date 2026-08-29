<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Accounts\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;

final readonly class AccountSnapshot
{
    public string $id;

    public ?string $name;

    public ?string $musicOnHoldMediaId;

    public ?string $timezone;

    public ?string $realm;

    public ?string $organizationName;

    public ?string $language;

    public bool $enabled;

    public bool $callWaitingEnabled;

    public bool $doNotDisturbEnabled;

    public string $outboundPrivacy;

    public bool $showRate;

    public ?string $internalCallerIdName;

    public ?string $internalCallerIdNumber;

    public ?string $externalCallerIdName;

    public ?string $externalCallerIdNumber;

    public ?string $emergencyCallerIdName;

    public ?string $emergencyCallerIdNumber;

    public ?string $internalRingtone;

    public ?string $externalRingtone;

    /** @var list<string> */
    public array $blacklistIds;

    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
        $id = $data['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new InvalidSwitchPayloadException('Switch account must contain a non-empty id.');
        }

        $musicOnHold = is_array($data['music_on_hold'] ?? null) ? $data['music_on_hold'] : [];
        $callWaiting = is_array($data['call_waiting'] ?? null) ? $data['call_waiting'] : [];
        $doNotDisturb = is_array($data['do_not_disturb'] ?? null) ? $data['do_not_disturb'] : [];
        $callerIdOptions = is_array($data['caller_id_options'] ?? null) ? $data['caller_id_options'] : [];
        $callerId = is_array($data['caller_id'] ?? null) ? $data['caller_id'] : [];
        $internalCallerId = is_array($callerId['internal'] ?? null) ? $callerId['internal'] : [];
        $externalCallerId = is_array($callerId['external'] ?? null) ? $callerId['external'] : [];
        $emergencyCallerId = is_array($callerId['emergency'] ?? null) ? $callerId['emergency'] : [];
        $ringtones = is_array($data['ringtones'] ?? null) ? $data['ringtones'] : [];
        $this->id = $id;
        $this->name = $this->nullableString($data['name'] ?? null);
        $this->musicOnHoldMediaId = $this->nullableString($musicOnHold['media_id'] ?? null);
        $this->timezone = $this->nullableString($data['timezone'] ?? null);
        $this->realm = $this->nullableString($data['realm'] ?? null);
        $this->organizationName = $this->nullableString($data['org'] ?? null);
        $this->language = $this->nullableString($data['language'] ?? null);
        $this->enabled = ($data['enabled'] ?? true) !== false;
        $this->callWaitingEnabled = ($callWaiting['enabled'] ?? true) !== false;
        $this->doNotDisturbEnabled = ($doNotDisturb['enabled'] ?? false) === true;
        $this->outboundPrivacy = $this->nullableString($callerIdOptions['outbound_privacy'] ?? null) ?? 'none';
        $this->showRate = ($callerIdOptions['show_rate'] ?? false) === true;
        $this->internalCallerIdName = $this->nullableString($internalCallerId['name'] ?? null);
        $this->internalCallerIdNumber = $this->nullableString($internalCallerId['number'] ?? null);
        $this->externalCallerIdName = $this->nullableString($externalCallerId['name'] ?? null);
        $this->externalCallerIdNumber = $this->nullableString($externalCallerId['number'] ?? null);
        $this->emergencyCallerIdName = $this->nullableString($emergencyCallerId['name'] ?? null);
        $this->emergencyCallerIdNumber = $this->nullableString($emergencyCallerId['number'] ?? null);
        $this->internalRingtone = $this->nullableString($ringtones['internal'] ?? null);
        $this->externalRingtone = $this->nullableString($ringtones['external'] ?? null);
        $blacklists = is_array($data['blacklists'] ?? null) ? $data['blacklists'] : [];
        $this->blacklistIds = array_values(array_filter($blacklists, static fn (mixed $id): bool => is_string($id) && $id !== ''));
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
