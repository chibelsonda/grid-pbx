<?php

namespace App\Domains\Blacklists\Gateways;

use App\Domains\Blacklists\Contracts\SwitchBlacklistGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Generator;
use GridPbx\Switch\Dto\Accounts\AccountBlacklistsWriteData;
use GridPbx\Switch\Dto\Blacklists\BlacklistWriteData;
use GridPbx\Switch\Resources\AccountResourceClient;
use GridPbx\Switch\Resources\BlacklistResourceClient;

class CrossbarSwitchBlacklistGateway implements SwitchBlacklistGateway
{
    public function __construct(private readonly BlacklistResourceClient $blacklists, private readonly AccountResourceClient $accounts) {}
    public function all(SwitchAccount $account): Generator { foreach ($this->blacklists->allDetails($account->switch_account_id) as $blacklist) yield $blacklist->toArray(); }
    public function activeIds(SwitchAccount $account): array { return $this->accounts->account($account->switch_account_id)->blacklistIds; }
    public function create(SwitchAccount $account, array $data): array { return $this->blacklists->create($account->switch_account_id, $this->writeData($data))->toArray(); }
    public function update(SwitchAccount $account, string $resourceId, array $data): array { return $this->blacklists->update($account->switch_account_id, $resourceId, $this->writeData($data))->toArray(); }
    public function setActiveIds(SwitchAccount $account, array $resourceIds): void { $this->accounts->updateBlacklists($account->switch_account_id, new AccountBlacklistsWriteData($resourceIds)); }
    public function delete(SwitchAccount $account, string $resourceId): void { $this->blacklists->delete($account->switch_account_id, $resourceId); }
    private function writeData(array $data): BlacklistWriteData { return new BlacklistWriteData((string) $data['name'], array_values($data['numbers'] ?? []), (bool) ($data['should_block_anonymous'] ?? false)); }
}
