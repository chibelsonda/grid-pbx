<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Users;

use GridPbx\Switch\Domains\Users\Dto\UserDirectoryMappingsWriteData;
use GridPbx\Switch\Domains\Users\Dto\UserSnapshot;
use GridPbx\Switch\Domains\Users\Dto\UserWriteData;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class UserResourceClient
{
    public function __construct(private SwitchClient $client) {}

    public function create(string $accountId, UserWriteData $user): UserSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $payload = $this->client->request(
            'PUT',
            sprintf('accounts/%s/users', rawurlencode($accountId)),
            ['json' => ['data' => $user->toSwitchData()]],
        );

        return $this->snapshot($payload);
    }

    public function get(string $accountId, string $userId): UserSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $userId = $this->requiredIdentifier($userId, 'user');

        return $this->snapshot($this->client->request('GET', sprintf(
            'accounts/%s/users/%s',
            rawurlencode($accountId),
            rawurlencode($userId),
        )));
    }

    public function updateDirectoryMappings(
        string $accountId,
        string $userId,
        UserDirectoryMappingsWriteData $directories,
    ): UserSnapshot {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $userId = $this->requiredIdentifier($userId, 'user');
        $payload = $this->client->request('PATCH', sprintf(
            'accounts/%s/users/%s',
            rawurlencode($accountId),
            rawurlencode($userId),
        ), ['json' => ['data' => $directories->toSwitchData()]]);
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $userId) {
            throw new InvalidSwitchPayloadException('Switch user response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function update(string $accountId, string $userId, UserWriteData $user): UserSnapshot
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $userId = $this->requiredIdentifier($userId, 'user');
        $data = $user->toSwitchData();

        if ($user->hotdesk?->preservePin === true) {
            $current = $this->get($accountId, $userId);
            $pin = $current->data['hotdesk']['pin'] ?? null;

            if (! is_string($pin) || $pin === '') {
                throw new InvalidSwitchPayloadException('Switch user has no configured hotdesk PIN to preserve.');
            }

            $data['hotdesk']['pin'] = $pin;
        }

        $payload = $this->client->request(
            'POST',
            sprintf(
                'accounts/%s/users/%s',
                rawurlencode($accountId),
                rawurlencode($userId),
            ),
            ['json' => ['data' => $data]],
        );
        $snapshot = $this->snapshot($payload);

        if ($snapshot->id !== $userId) {
            throw new InvalidSwitchPayloadException('Switch user response id does not match the requested resource.');
        }

        return $snapshot;
    }

    public function delete(string $accountId, string $userId): void
    {
        $accountId = $this->requiredIdentifier($accountId, 'account');
        $userId = $this->requiredIdentifier($userId, 'user');
        $this->client->request(
            'DELETE',
            sprintf(
                'accounts/%s/users/%s',
                rawurlencode($accountId),
                rawurlencode($userId),
            ),
        );
    }

    /** @param array<string, mixed> $payload */
    private function snapshot(array $payload): UserSnapshot
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException('Switch user response data must be an object.');
        }

        return new UserSnapshot($data);
    }

    private function requiredIdentifier(string $identifier, string $name): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException(sprintf('Switch %s identifier is required.', $name));
        }

        return $identifier;
    }
}
