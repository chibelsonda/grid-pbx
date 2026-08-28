<?php

namespace App\Domains\LineKeys\Gateways;

use App\Domains\LineKeys\Contracts\SwitchLineKeyGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Dto\LineKeys\LineKeyWriteData;
use GridPbx\Switch\Resources\LineKeyResourceClient;

class CrossbarSwitchLineKeyGateway implements SwitchLineKeyGateway
{
    public function __construct(private readonly LineKeyResourceClient $lineKeys) {}

    public function update(SwitchAccount $account, string $deviceResourceId, array $keys): array
    {
        return $this->lineKeys->update(
            $account->switch_account_id,
            $deviceResourceId,
            array_map(
                static fn (array $key): LineKeyWriteData => new LineKeyWriteData(
                    category: $key['category'],
                    position: $key['position'],
                    type: $key['type'],
                    value: $key['value'] ?? null,
                    label: $key['label'] ?? null,
                ),
                $keys,
            ),
        )->device;
    }
}
