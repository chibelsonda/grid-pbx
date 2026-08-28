<?php

namespace App\Domains\Faxes\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;
use Generator;
use GridPbx\Switch\Http\BinaryResponse;

interface SwitchFaxGateway
{
    /** @return Generator<int, array<string, mixed>> */ public function all(SwitchAccount $account, string $folder, CarbonImmutable $from, CarbonImmutable $to): Generator;
    public function document(SwitchAccount $account, string $folder, string $resourceId, ?string $range = null): BinaryResponse;
}
