<?php

namespace App\Domains\CallRouting\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;

interface SwitchCallflowEntryPointGateway
{
    /** @return array<string, mixed> */
    public function updateEntryPoints(
        SwitchAccount $account,
        string $resourceId,
        array $assignedEntryNumbers,
        array $knownEntryNumbers,
    ): array;
}
