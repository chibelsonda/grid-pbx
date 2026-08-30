<?php

namespace App\Domains\CallerIdLists\Gateways;

use App\Domains\CallerIdLists\Contracts\SwitchCallerIdListGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Generator;
use GridPbx\Switch\Domains\CallerIdLists\CallerIdListResourceClient;
use GridPbx\Switch\Domains\CallerIdLists\Dto\CallerIdListEntryWriteData;
use GridPbx\Switch\Domains\CallerIdLists\Dto\CallerIdListWriteData;

class CrossbarSwitchCallerIdListGateway implements SwitchCallerIdListGateway
{
    public function __construct(private readonly CallerIdListResourceClient $lists) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->lists->allDetails($account->switch_account_id) as $details) {
            yield [
                'list' => $details->list->toArray(),
                'entries' => array_map(
                    fn ($entry): array => $entry->toArray(),
                    $details->entries,
                ),
            ];
        }
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->lists->create($account->switch_account_id, $this->listWriteData($data))->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->lists->update($account->switch_account_id, $resourceId, $this->listWriteData($data))->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->lists->delete($account->switch_account_id, $resourceId);
    }

    public function createEntry(SwitchAccount $account, string $listResourceId, array $data): array
    {
        return $this->lists->createEntry(
            $account->switch_account_id,
            $listResourceId,
            $this->entryWriteData($data),
        )->toArray();
    }

    public function updateEntry(SwitchAccount $account, string $listResourceId, string $entryResourceId, array $data): array
    {
        return $this->lists->updateEntry(
            $account->switch_account_id,
            $listResourceId,
            $entryResourceId,
            $this->entryWriteData($data),
        )->toArray();
    }

    public function deleteEntry(SwitchAccount $account, string $listResourceId, string $entryResourceId): void
    {
        $this->lists->deleteEntry($account->switch_account_id, $listResourceId, $entryResourceId);
    }

    public function details(SwitchAccount $account, string $resourceId): array
    {
        $details = $this->lists->details($account->switch_account_id, $resourceId);

        return [
            'list' => $details->list->toArray(),
            'entries' => array_map(fn ($entry): array => $entry->toArray(), $details->entries),
        ];
    }

    /** @param array<string, mixed> $data */
    private function listWriteData(array $data): CallerIdListWriteData
    {
        return new CallerIdListWriteData(
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            organization: isset($data['organization']) ? (string) $data['organization'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    private function entryWriteData(array $data): CallerIdListEntryWriteData
    {
        return new CallerIdListEntryWriteData(
            displayName: isset($data['display_name']) ? (string) $data['display_name'] : null,
            number: isset($data['number']) ? (string) $data['number'] : null,
            pattern: isset($data['pattern']) ? (string) $data['pattern'] : null,
        );
    }
}
