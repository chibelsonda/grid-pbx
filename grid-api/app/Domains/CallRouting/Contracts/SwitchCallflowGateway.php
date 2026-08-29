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
        string $destinationResourceId,
        array $phoneNumbers,
        ?string $fallbackModule = null,
        ?string $fallbackResourceId = null,
        array $branchRoutes = [],
    ): array;

    /** @return array<string, mixed> */
    public function updateDestination(
        SwitchAccount $account,
        string $resourceId,
        string $destinationModule,
        string $destinationResourceId,
        ?string $name,
        array $assignedPhoneNumbers,
        array $knownPhoneNumbers,
        bool $replaceFallback = false,
        ?string $fallbackModule = null,
        ?string $fallbackResourceId = null,
        array $branchOperations = [],
    ): array;

    public function delete(SwitchAccount $account, string $resourceId): void;
}
