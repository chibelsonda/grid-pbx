<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users\Dto\Profile;

use stdClass;

final readonly class UserProfileData
{
    /**
     * @param  list<UserProfileAddressData>  $addresses
     * @param  list<string>  $nicknames
     * @param  array<string, mixed>  $preservedOptions
     */
    public function __construct(
        public array $addresses = [],
        public ?string $assistant = null,
        public ?string $birthday = null,
        public array $nicknames = [],
        public ?string $note = null,
        public ?string $role = null,
        public ?string $sortString = null,
        public ?string $title = null,
        public array $preservedOptions = [],
    ) {}

    /** @return array<string, mixed>|stdClass */
    public function toSwitchData(): array|stdClass
    {
        $data = array_merge($this->preservedOptions, array_filter([
            'assistant' => $this->assistant,
            'birthday' => $this->birthday,
            'note' => $this->note,
            'role' => $this->role,
            'sort-string' => $this->sortString,
            'title' => $this->title,
        ], static fn (?string $value): bool => $value !== null));

        if ($this->addresses !== []) {
            $data['addresses'] = array_map(
                static fn (UserProfileAddressData $address): array => $address->toSwitchData(),
                $this->addresses,
            );
        }

        if ($this->nicknames !== []) {
            $data['nicknames'] = $this->nicknames;
        }

        return $data === [] ? new stdClass : $data;
    }
}
