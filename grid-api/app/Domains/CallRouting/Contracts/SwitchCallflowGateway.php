<?php

namespace App\Domains\CallRouting\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchCallflowGateway
{
    /** @return array<string, mixed> */
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
    ): array;

    /** @return array<string, mixed> */
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
    ): array;

    /**
     * @param  list<string>  $sourcePath
     * @param  list<string>  $destinationParentPath
     * @return array<string, mixed>
     */
    public function moveTreeNode(
        SwitchAccount $account,
        string $resourceId,
        array $sourcePath,
        array $destinationParentPath,
        string $destinationBranch,
    ): array;

    /**
     * @param  list<string>  $path
     * @return array<string, mixed>
     */
    public function writeTreeNode(
        SwitchAccount $account,
        string $resourceId,
        string $operation,
        array $path,
        ?string $branch,
        string $module,
        string $targetResourceId,
    ): array;

    /** @param list<string> $path @return array<string, mixed> */
    public function deleteTreeNode(
        SwitchAccount $account,
        string $resourceId,
        array $path,
    ): array;

    /** @param list<string> $sourcePath @param list<string> $targetPath @return array<string, mixed> */
    public function reorderTreeNodes(
        SwitchAccount $account,
        string $resourceId,
        string $mode,
        array $sourcePath,
        array $targetPath,
    ): array;

    /** @param list<string> $path @param array<string, mixed> $settings @return array<string, mixed> */
    public function writeInlineTreeNode(
        SwitchAccount $account,
        string $resourceId,
        string $operation,
        array $path,
        ?string $branch,
        string $module,
        array $settings,
        string $placement = 'append',
    ): array;

    public function delete(SwitchAccount $account, string $resourceId): void;
}
