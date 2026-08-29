<?php

namespace App\Domains\Menus\Gateways;

use App\Domains\Menus\Contracts\SwitchMenuGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use Generator;
use GridPbx\Switch\Domains\Menus\Dto\MenuWriteData;
use GridPbx\Switch\Domains\Menus\MenuResourceClient;

class CrossbarSwitchMenuGateway implements SwitchMenuGateway
{
    public function __construct(private readonly MenuResourceClient $menus) {}

    public function all(SwitchAccount $account): Generator
    {
        foreach ($this->menus->allDetails($account->switch_account_id) as $menu) {
            yield $menu->toArray();
        }
    }

    public function create(SwitchAccount $account, array $data): array
    {
        return $this->menus->create($account->switch_account_id, $this->writeData($data))->toArray();
    }

    public function update(SwitchAccount $account, string $resourceId, array $data): array
    {
        return $this->menus->update($account->switch_account_id, $resourceId, $this->writeData($data))->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->menus->delete($account->switch_account_id, $resourceId);
    }

    /** @param array<string, mixed> $data */
    private function writeData(array $data): MenuWriteData
    {
        return new MenuWriteData(
            name: (string) $data['name'], timeout: (int) $data['timeout'],
            interdigitTimeout: (int) $data['interdigit_timeout'], maxExtensionLength: (int) $data['max_extension_length'],
            retries: (int) $data['retries'], hunt: (bool) $data['hunt'],
            allowRecordFromOffnet: (bool) $data['allow_record_from_offnet'], suppressMedia: (bool) $data['suppress_media'],
            recordPin: $data['record_pin'] ?? null, huntAllow: $data['hunt_allow'] ?? null, huntDeny: $data['hunt_deny'] ?? null,
            greetingMediaId: $data['switch_greeting_media_reference'] ?? null,
            invalidMedia: $data['switch_invalid_media'] ?? true,
            transferMedia: $data['switch_transfer_media'] ?? true,
            exitMedia: $data['switch_exit_media'] ?? true,
        );
    }
}
