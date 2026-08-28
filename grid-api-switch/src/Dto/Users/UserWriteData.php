<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Users;

use InvalidArgumentException;

final readonly class UserWriteData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $extension,
        public bool $enabled = true,
        public ?string $username = null,
        public ?string $email = null,
        public ?string $timezone = null,
        public ?UserAdvancedData $advanced = null,
    ) {
        if (trim($this->firstName) === '') {
            throw new InvalidArgumentException('Switch user first name is required.');
        }

        if (trim($this->lastName) === '') {
            throw new InvalidArgumentException('Switch user last name is required.');
        }

        if (trim($this->extension) === '') {
            throw new InvalidArgumentException('Switch user extension number is required.');
        }
    }

    /** @return array<string, mixed> */
    public function toSwitchData(): array
    {
        $data = [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'enabled' => $this->enabled,
            'caller_id' => [
                'internal' => [
                    'name' => trim("{$this->firstName} {$this->lastName}"),
                    'number' => $this->extension,
                ],
            ],
            'presence_id' => $this->extension,
        ];

        foreach (['username', 'email', 'timezone'] as $field) {
            $value = $this->{$field};

            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        return array_replace($data, $this->advanced?->toSwitchData() ?? []);
    }
}
