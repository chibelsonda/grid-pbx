<?php

namespace App\Domains\Media\Gateways;

use App\Domains\Media\Contracts\SwitchMediaGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Generator;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Accounts\Dto\MusicOnHoldWriteData;
use GridPbx\Switch\Domains\Media\Dto\MediaWriteData;
use GridPbx\Switch\Domains\Media\MediaResourceClient;
use GridPbx\Switch\Shared\Http\BinaryResponse;
use Psr\Http\Message\StreamInterface;

class CrossbarSwitchMediaGateway implements SwitchMediaGateway
{
    public function __construct(
        private readonly MediaResourceClient $media,
        private readonly AccountResourceClient $accounts,
    ) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->media->allDetails($account->switch_account_id) as $snapshot) {
            yield $snapshot->toArray();
        }
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->media->create(
            $account->switch_account_id,
            $this->writeData($data),
        )->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->media->update(
            $account->switch_account_id,
            $resourceId,
            $this->writeData($data),
        )->toArray();
    }

    public function upload(
        SwitchAccount $account,
        string $resourceId,
        StreamInterface $stream,
        string $contentType,
        int $contentLength,
    ): array {
        return $this->media->upload(
            $account->switch_account_id,
            $resourceId,
            $stream,
            $contentType,
            $contentLength,
        )->toArray();
    }

    public function audio(SwitchAccount $account, string $resourceId, ?string $range = null): BinaryResponse
    {
        return $this->media->raw($account->switch_account_id, $resourceId, $range);
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->media->delete($account->switch_account_id, $resourceId);
    }

    public function accountMusicOnHold(SwitchAccount $account): ?string
    {
        return $this->accounts->account($account->switch_account_id)->musicOnHoldMediaId;
    }

    public function updateAccountMusicOnHold(SwitchAccount $account, ?string $resourceId): ?string
    {
        return $this->accounts->updateMusicOnHold(
            $account->switch_account_id,
            new MusicOnHoldWriteData($resourceId),
        )->musicOnHoldMediaId;
    }

    /** @param array<string, mixed> $data */
    private function writeData(array $data): MediaWriteData
    {
        return new MediaWriteData(
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            mediaSource: isset($data['media_source']) ? (string) $data['media_source'] : 'upload',
            streamable: (bool) ($data['streamable'] ?? true),
            language: isset($data['language']) ? (string) $data['language'] : null,
        );
    }
}
