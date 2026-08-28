<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto;

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
    }
}
