<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Accounts;

use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

final readonly class AccountSnapshot
{
    public string $id;

    public ?string $name;

    public ?string $musicOnHoldMediaId;

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
        $this->id = $id;
        $this->name = $this->nullableString($data['name'] ?? null);
        $this->musicOnHoldMediaId = $this->nullableString($musicOnHold['media_id'] ?? null);
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
