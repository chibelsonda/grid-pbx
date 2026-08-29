<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Credentials;

use InvalidArgumentException;

final readonly class UserCredentialsData
{
    public function __construct(
        public ?string $username = null,
        public ?string $password = null,
        public bool $requirePasswordUpdate = false,
    ) {
        if ($this->username !== null) {
            if (
                $this->username === ''
                || mb_strlen($this->username) > 256
                || preg_match('/^[+@.\w_-]+$/', $this->username) !== 1
            ) {
                throw new InvalidArgumentException('Switch user login username is invalid.');
            }
        }

        if ($this->password !== null && $this->password === '') {
            throw new InvalidArgumentException('Switch user login password cannot be empty.');
        }

        if ($this->username === null && $this->password !== null) {
            throw new InvalidArgumentException('Switch user login password requires a username.');
        }

        if ($this->username === null && $this->requirePasswordUpdate) {
            throw new InvalidArgumentException('Switch user password update requirement needs login credentials.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = ['require_password_update' => $this->requirePasswordUpdate];

        if ($this->username !== null) {
            $data['username'] = $this->username;
        }

        if ($this->password !== null) {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
