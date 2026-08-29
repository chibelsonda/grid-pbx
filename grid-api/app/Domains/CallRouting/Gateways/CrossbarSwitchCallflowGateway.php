<?php

namespace App\Domains\CallRouting\Gateways;

use App\Domains\CallRouting\Contracts\SwitchCallflowGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Accounts\AccountResource;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Callflows\CallflowResourceClient;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowBranchWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowCreateData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowWriteData;
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
        ?string $destinationResourceId,
        array $phoneNumbers,
        ?string $fallbackModule = null,
        ?string $fallbackResourceId = null,
        array $branchRoutes = [],
        array $destinationTemporalRuleIds = [],
    ): array {
        return $this->callflows->create(
            $account->switch_account_id,
            new CallflowCreateData(
                name: $name,
                destinationModule: $destinationModule,
                destinationResourceId: $destinationResourceId,
                phoneNumbers: $phoneNumbers,
                fallbackModule: $fallbackModule,
                fallbackResourceId: $fallbackResourceId,
                branchRoutes: $this->branchData($branchRoutes),
                destinationTemporalRuleIds: $destinationTemporalRuleIds,
            ),
        )->toArray();
    }

    public function updateDestination(
        SwitchAccount $account,
        string $resourceId,
        string $destinationModule,
        ?string $destinationResourceId,
        ?string $name,
        array $assignedPhoneNumbers,
        array $knownPhoneNumbers,
        bool $replaceFallback = false,
        ?string $fallbackModule = null,
        ?string $fallbackResourceId = null,
        array $branchOperations = [],
        array $destinationTemporalRuleIds = [],
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
                replaceFallback: $replaceFallback,
                fallbackModule: $fallbackModule,
                fallbackResourceId: $fallbackResourceId,
                branchOperations: $this->branchData($branchOperations),
                destinationTemporalRuleIds: $destinationTemporalRuleIds,
            ),
        )->toArray();
    }

    public function delete(SwitchAccount $account, string $resourceId): void
    {
        $this->callflows->delete($account->switch_account_id, $resourceId);
    }

    /**
     * @param  list<array{key: string, module: ?string, resource_id: ?string}>  $branches
     * @return list<CallflowBranchWriteData>
     */
    private function branchData(array $branches): array
    {
        return array_map(
            fn (array $branch): CallflowBranchWriteData => new CallflowBranchWriteData(
                key: $branch['key'],
                module: $branch['module'],
                resourceId: $branch['resource_id'],
            ),
            $branches,
        );
    }
}
