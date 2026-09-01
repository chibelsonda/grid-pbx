<?php

namespace App\Domains\Conferences\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Generator;

interface SwitchConferenceGateway
{
    /** @return Generator<int, array<string, mixed>> */
    public function all(SwitchAccount $account): Generator;

    /** @return array<string, mixed> */
    public function get(SwitchAccount $account, string $resourceId): array;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(SwitchAccount $account, array $data): array;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(SwitchAccount $account, string $resourceId, array $data): array;

    public function delete(SwitchAccount $account, string $resourceId): void;

    public function setLocked(SwitchAccount $account, string $resourceId, bool $locked): void;

    /** @return list<array<string, mixed>> */
    public function participants(SwitchAccount $account, string $resourceId): array;

    public function controlParticipant(
        SwitchAccount $account,
        string $resourceId,
        string $participantId,
        string $action,
    ): void;

    public function controlParticipants(SwitchAccount $account, string $resourceId, string $action): void;

    public function playMedia(
        SwitchAccount $account,
        string $resourceId,
        string $mediaResourceId,
        ?string $participantId = null,
    ): void;
}
