<?php

namespace App\Domains\Groups\Gateways;

use App\Domains\Groups\Contracts\SwitchGroupGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Generator;
use GridPbx\Switch\Dto\Groups\GroupEndpointWriteData;
use GridPbx\Switch\Dto\Groups\GroupWriteData;
use GridPbx\Switch\Resources\GroupResourceClient;

class CrossbarSwitchGroupGateway implements SwitchGroupGateway
{
    public function __construct(private readonly GroupResourceClient $groups) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->groups->allDetails($account->switch_account_id) as $group) {
            yield $group->toArray();
        }
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->groups->create($account->switch_account_id, $this->writeData($data))->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->groups->update($account->switch_account_id, $resourceId, $this->writeData($data))->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->groups->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $data */
    private function writeData(array $data): GroupWriteData
    {
        return new GroupWriteData(
            name: (string) $data['name'],
            endpoints: array_map(fn (array $member): GroupEndpointWriteData => new GroupEndpointWriteData(
                resourceId: (string) $member['switch_resource_id'],
                type: (string) $member['type'],
                weight: (int) $member['weight'],
            ), $data['resolved_members']),
            musicOnHoldMediaId: $data['switch_music_on_hold_media_id'] ?? null,
        );
    }
}
