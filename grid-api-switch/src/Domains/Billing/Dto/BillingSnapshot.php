<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Billing\Dto;

final readonly class BillingSnapshot
{
    /**
     * @param  list<LedgerSummarySnapshot>  $ledgers
     * @param  list<TransactionSnapshot>  $transactions
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $ledgersAvailable,
        public bool $ledgerTotalAvailable,
        public bool $transactionsAvailable,
        public ?string $ledgerTotal,
        public array $ledgers,
        public array $transactions,
        public array $data,
    ) {}
}
