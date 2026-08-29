<?php

namespace App\Domains\Media\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Generator;
use GridPbx\Switch\Shared\Http\BinaryResponse;
use Psr\Http\Message\StreamInterface;

interface SwitchMediaGateway
{
    /** @return Generator<int, array<string, mixed>> */
    public function all(SwitchAccount $account): Generator;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(SwitchAccount $account, array $data): array;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(SwitchAccount $account, string $resourceId, array $data): array;

    /** @return array<string, mixed> */
    public function upload(
        SwitchAccount $account,
        string $resourceId,
        StreamInterface $stream,
        string $contentType,
        int $contentLength,
    ): array;

    public function audio(SwitchAccount $account, string $resourceId, ?string $range = null): BinaryResponse;

    public function delete(SwitchAccount $account, string $resourceId): void;

    public function accountMusicOnHold(SwitchAccount $account): ?string;

    public function updateAccountMusicOnHold(SwitchAccount $account, ?string $resourceId): ?string;
}
