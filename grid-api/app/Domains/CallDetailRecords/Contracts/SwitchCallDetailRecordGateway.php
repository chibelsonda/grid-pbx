<?php

namespace App\Domains\CallDetailRecords\Contracts;

use App\Domains\Organizations\Models\SwitchAccount;
use DateTimeInterface;
use Generator;

interface SwitchCallDetailRecordGateway
{
    /** @return Generator<int, array<string, mixed>> */
    public function all(SwitchAccount $account, DateTimeInterface $from, DateTimeInterface $to): Generator;
}
