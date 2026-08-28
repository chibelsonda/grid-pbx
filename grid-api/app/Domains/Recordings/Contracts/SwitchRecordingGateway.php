<?php

namespace App\Domains\Recordings\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use Carbon\CarbonImmutable;
use Generator;
use GridPbx\Switch\Http\BinaryResponse;

interface SwitchRecordingGateway
{
    /** @return Generator<int, array<string, mixed>> */ public function all(SwitchAccount $account, CarbonImmutable $from, CarbonImmutable $to): Generator;
    public function audio(SwitchAccount $account, string $resourceId, ?string $range = null): BinaryResponse;
}
