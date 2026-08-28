<?php

namespace App\Domains\Directories\Gateways;

use App\Domains\Directories\Contracts\SwitchDirectoryGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Generator;
use GridPbx\Switch\Dto\Directories\DirectoryWriteData;
use GridPbx\Switch\Dto\Users\UserDirectoryMappingsWriteData;
use GridPbx\Switch\Resources\DirectoryResourceClient;
use GridPbx\Switch\Resources\UserResourceClient;
use Throwable;

class CrossbarSwitchDirectoryGateway implements SwitchDirectoryGateway
{
    public function __construct(
        private readonly DirectoryResourceClient $directories,
        private readonly UserResourceClient $users,
    ) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->directories->allDetails($account->switch_account_id) as $directory) {
            yield $directory->toArray();
        }
    }

    public function get(SwitchAccount $account, string $resourceId): array
    {
        return $this->directories->get($account->switch_account_id, $resourceId)->toArray();
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->directories->create($account->switch_account_id, $this->writeData($data))->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->directories->update($account->switch_account_id, $resourceId, $this->writeData($data))->toArray();
    }

    public function replaceMembers(SwitchAccount $account, string $resourceId, array $members): array
    {
        $current = $this->directories->get($account->switch_account_id, $resourceId);
        $currentMembers = [];

        foreach ($current->members as $member) {
            $currentMembers[$member->userId] = $member->callflowId;
        }

        $originalMappings = [];
        $updatedUsers = [];

        try {
            foreach (array_values(array_unique([...array_keys($currentMembers), ...array_keys($members)])) as $userId) {
                $user = $this->users->get($account->switch_account_id, $userId);
                $originalMappings[$userId] = $user->directoryMappings;
                $next = $user->directoryMappings;

                if (isset($members[$userId])) {
                    $next[$resourceId] = $members[$userId];
                } else {
                    unset($next[$resourceId]);
                }

                if ($next !== $user->directoryMappings) {
                    $this->users->updateDirectoryMappings(
                        $account->switch_account_id,
                        $userId,
                        new UserDirectoryMappingsWriteData($next),
                    );
                    $updatedUsers[] = $userId;
                }
            }
        } catch (Throwable $exception) {
            foreach (array_reverse($updatedUsers) as $userId) {
                try {
                    $this->users->updateDirectoryMappings(
                        $account->switch_account_id,
                        $userId,
                        new UserDirectoryMappingsWriteData($originalMappings[$userId]),
                    );
                } catch (Throwable) {
                }
            }

            throw $exception;
        }

        return $this->get($account, $resourceId);
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->directories->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $data */
    private function writeData(array $data): DirectoryWriteData
    {
        return new DirectoryWriteData(
            name: (string) $data['name'],
            confirmMatch: (bool) ($data['confirm_match'] ?? true),
            minDtmf: (int) ($data['min_dtmf'] ?? 3),
            maxDtmf: (int) ($data['max_dtmf'] ?? 0),
            sortBy: (string) ($data['sort_by'] ?? 'last_name'),
        );
    }
}
