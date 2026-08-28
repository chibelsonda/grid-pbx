<?php

namespace App\Domains\CallRouting\Gateways;

use App\Domains\CallRouting\Contracts\SwitchCallflowGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Dto\Callflows\CallflowCreateData;
use GridPbx\Switch\Dto\Callflows\CallflowSnapshot;
use GridPbx\Switch\Dto\Callflows\CallflowWriteData;
use GridPbx\Switch\Resources\AccountResource;
use GridPbx\Switch\Resources\AccountResourceClient;
use GridPbx\Switch\Resources\CallflowResourceClient;
use UnexpectedValueException;

class CrossbarSwitchCallflowGateway implements SwitchCallflowGateway
{
    public function __construct(
        private readonly AccountResourceClient $resources,
        private readonly CallflowResourceClient $callflows,
    ) {}

    public function create(
        SwitchAccount $account,
        string $name,
        string $destinationModule,
        string $destinationResourceId,
        array $phoneNumbers,
    ): array {
        return $this->callflows->create(
            $account->switch_account_id,
            new CallflowCreateData(
                name: $name,
                destinationModule: $destinationModule,
                destinationResourceId: $destinationResourceId,
                phoneNumbers: $phoneNumbers,
            ),
        )->toArray();
    }

    public function updateDestination(
        SwitchAccount $account,
        string $resourceId,
        string $destinationModule,
        string $destinationResourceId,
        ?string $name,
        array $assignedPhoneNumbers,
        array $knownPhoneNumbers,
    ): array {
        $current = $this->resources->find(
            $account->switch_account_id,
            AccountResource::Callflows,
            $resourceId,
        );

        if (! $current instanceof CallflowSnapshot) {
            throw new UnexpectedValueException('Switch returned an unexpected callflow resource.');
        }

        return $this->callflows->update(
            $account->switch_account_id,
            $resourceId,
            new CallflowWriteData(
                current: $current->toArray(),
                destinationModule: $destinationModule,
                destinationResourceId: $destinationResourceId,
                name: $name,
                assignedPhoneNumbers: $assignedPhoneNumbers,
                knownPhoneNumbers: $knownPhoneNumbers,
            ),
        )->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->callflows->delete($account->switch_account_id, $resourceId);
    }
}
