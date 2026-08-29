<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto;

use GridPbx\Switch\Shared\Dto\EntitySnapshot;

final readonly class UserSnapshot extends EntitySnapshot
{
    public ?string $username;

    public ?string $firstName;

    public ?string $lastName;

    public ?string $email;

    public ?string $presenceId;

    public ?string $internalCallerIdNumber;

    public ?string $timezone;

    public bool $enabled;

    public bool $requirePasswordUpdate;

    public bool $hotdeskEnabled;

    public ?string $hotdeskId;

    public bool $hotdeskKeepLoggedInElsewhere;

    public bool $hotdeskRequirePin;

    public bool $hotdeskPinConfigured;

    /** @var array<string, string> */
    public array $directoryMappings;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->username = $this->nullableString($data['username'] ?? null);
        $this->firstName = $this->nullableString($data['first_name'] ?? null);
        $this->lastName = $this->nullableString($data['last_name'] ?? null);
        $this->email = $this->nullableString($data['email'] ?? null);
        $this->presenceId = $this->nullableString($data['presence_id'] ?? null);
        $this->internalCallerIdNumber = $this->nestedString('caller_id', 'internal', 'number');
        $this->timezone = $this->nullableString($data['timezone'] ?? null);
        $this->enabled = (bool) ($data['enabled'] ?? true);
        $this->requirePasswordUpdate = (bool) ($data['require_password_update'] ?? false);
        $hotdesk = is_array($data['hotdesk'] ?? null) ? $data['hotdesk'] : [];
        $this->hotdeskEnabled = (bool) ($hotdesk['enabled'] ?? false);
        $this->hotdeskId = $this->nullableString($hotdesk['id'] ?? null);
        $this->hotdeskKeepLoggedInElsewhere = (bool) ($hotdesk['keep_logged_in_elsewhere'] ?? false);
        $this->hotdeskRequirePin = (bool) ($hotdesk['require_pin'] ?? false);
        $this->hotdeskPinConfigured = $this->nullableString($hotdesk['pin'] ?? null) !== null;
        $directories = $data['directories'] ?? [];
        $this->directoryMappings = is_array($directories)
            ? array_filter($directories, static fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH)
            : [];
    }
}
