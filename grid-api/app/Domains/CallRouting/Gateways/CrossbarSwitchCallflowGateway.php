<?php

namespace App\Domains\CallRouting\Gateways;

use App\Domains\CallRouting\Contracts\SwitchCallflowGateway;
use App\Domains\Organizations\Models\SwitchAccount;
use GridPbx\Switch\Domains\Accounts\AccountResource;
use GridPbx\Switch\Domains\Accounts\AccountResourceClient;
use GridPbx\Switch\Domains\Callflows\CallflowResourceClient;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowBranchWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowCreateData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowInlineNodeWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowSnapshot;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeMoveData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeNodeDeleteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeNodeWriteData;
use GridPbx\Switch\Domains\Callflows\Dto\CallflowTreeReorderData;
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
        array $entryNumbers,
        ?string $fallbackModule = null,
        ?string $fallbackResourceId = null,
        array $branchRoutes = [],
        array $destinationTemporalRuleIds = [],
        ?array $destinationSettings = null,
    ): array {
        return $this->callflows->create(
            $account->switch_account_id,
            new CallflowCreateData(
                name: $name,
                destinationModule: $destinationModule,
                destinationResourceId: $destinationResourceId,
                entryNumbers: $entryNumbers,
                fallbackModule: $fallbackModule,
                fallbackResourceId: $fallbackResourceId,
                branchRoutes: $this->branchData($branchRoutes),
                destinationTemporalRuleIds: $destinationTemporalRuleIds,
                destinationSettings: $destinationSettings,
            ),
        )->toArray();
    }

    public function updateDestination(
        SwitchAccount $account,
        string $resourceId,
        string $destinationModule,
        ?string $destinationResourceId,
        ?string $name,
        array $assignedEntryNumbers,
        array $knownEntryNumbers,
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
                assignedEntryNumbers: $assignedEntryNumbers,
                knownEntryNumbers: $knownEntryNumbers,
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

    public function moveTreeNode(
        SwitchAccount $account,
        string $resourceId,
        array $sourcePath,
        array $destinationParentPath,
        string $destinationBranch,
    ): array {
        $current = $this->resources->find(
            $account->switch_account_id,
            AccountResource::Callflows,
            $resourceId,
        );

        if (! $current instanceof CallflowSnapshot) {
            throw new UnexpectedValueException('Switch returned an unexpected callflow resource.');
        }

        return $this->callflows->moveTreeNode(
            $account->switch_account_id,
            $resourceId,
            new CallflowTreeMoveData(
                current: $current->toArray(),
                sourcePath: $sourcePath,
                destinationParentPath: $destinationParentPath,
                destinationBranch: $destinationBranch,
            ),
        )->toArray();
    }

    public function writeTreeNode(
        SwitchAccount $account,
        string $resourceId,
        string $operation,
        array $path,
        ?string $branch,
        string $module,
        string $targetResourceId,
    ): array {
        $current = $this->resources->find(
            $account->switch_account_id,
            AccountResource::Callflows,
            $resourceId,
        );

        if (! $current instanceof CallflowSnapshot) {
            throw new UnexpectedValueException('Switch returned an unexpected callflow resource.');
        }

        $write = $operation === 'create'
            ? CallflowTreeNodeWriteData::create(
                current: $current->toArray(),
                parentPath: $path,
                branch: (string) $branch,
                module: $module,
                resourceId: $targetResourceId,
            )
            : CallflowTreeNodeWriteData::update(
                current: $current->toArray(),
                nodePath: $path,
                module: $module,
                resourceId: $targetResourceId,
            );

        return $this->callflows->writeTreeNode(
            $account->switch_account_id,
            $resourceId,
            $write,
        )->toArray();
    }

    public function deleteTreeNode(
        SwitchAccount $account,
        string $resourceId,
        array $path,
    ): array {
        $current = $this->resources->find(
            $account->switch_account_id,
            AccountResource::Callflows,
            $resourceId,
        );

        if (! $current instanceof CallflowSnapshot) {
            throw new UnexpectedValueException('Switch returned an unexpected callflow resource.');
        }

        return $this->callflows->deleteTreeNode(
            $account->switch_account_id,
            $resourceId,
            new CallflowTreeNodeDeleteData($current->toArray(), $path),
        )->toArray();
    }

    public function reorderTreeNodes(
        SwitchAccount $account,
        string $resourceId,
        string $mode,
        array $sourcePath,
        array $targetPath,
    ): array {
        $current = $this->resources->find(
            $account->switch_account_id,
            AccountResource::Callflows,
            $resourceId,
        );

        if (! $current instanceof CallflowSnapshot) {
            throw new UnexpectedValueException('Switch returned an unexpected callflow resource.');
        }

        return $this->callflows->reorderTreeNodes(
            $account->switch_account_id,
            $resourceId,
            new CallflowTreeReorderData($current->toArray(), $mode, $sourcePath, $targetPath),
        )->toArray();
    }

    public function writeInlineTreeNode(
        SwitchAccount $account,
        string $resourceId,
        string $operation,
        array $path,
        ?string $branch,
        string $module,
        array $settings,
        string $placement = 'append',
    ): array {
        $current = $this->resources->find(
            $account->switch_account_id,
            AccountResource::Callflows,
            $resourceId,
        );

        if (! $current instanceof CallflowSnapshot) {
            throw new UnexpectedValueException('Switch returned an unexpected callflow resource.');
        }

        $write = $operation === 'create'
            ? CallflowInlineNodeWriteData::create(
                $current->toArray(),
                $path,
                (string) $branch,
                $module,
                $settings,
                $placement,
            )
            : CallflowInlineNodeWriteData::update(
                $current->toArray(),
                $path,
                $module,
                $settings,
            );

        return $this->callflows->writeInlineTreeNode(
            $account->switch_account_id,
            $resourceId,
            $write,
        )->toArray();
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
