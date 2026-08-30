<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Billing;

use GridPbx\Switch\Domains\Billing\Dto\BillingSnapshot;
use GridPbx\Switch\Domains\Billing\Dto\LedgerSummarySnapshot;
use GridPbx\Switch\Domains\Billing\Dto\TransactionSnapshot;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use GridPbx\Switch\Shared\Support\DecimalString;
use GridPbx\Switch\SwitchClient;
use InvalidArgumentException;

final readonly class BillingResourceClient
{
    public function __construct(private SwitchClient $client) {}

    public function snapshot(string $accountId): BillingSnapshot
    {
        $basePath = $this->accountPath($accountId);
        [$ledgersAvailable, $ledgerData] = $this->optionalData("{$basePath}/ledgers", 'ledger summary');
        [$ledgerTotalAvailable, $ledgerTotalData] = $this->optionalData("{$basePath}/ledgers/total", 'ledger total');
        [$transactionsAvailable, $transactionData] = $this->optionalData("{$basePath}/transactions", 'transactions');

        $ledgers = $ledgersAvailable ? $this->mapLedgers($ledgerData) : [];
        $transactions = $transactionsAvailable ? $this->mapTransactions($transactionData) : [];
        $ledgerTotal = $ledgerTotalAvailable
            ? DecimalString::fromMixed($ledgerTotalData['amount'] ?? null, 'ledger total amount')
            : null;

        return new BillingSnapshot(
            ledgersAvailable: $ledgersAvailable,
            ledgerTotalAvailable: $ledgerTotalAvailable,
            transactionsAvailable: $transactionsAvailable,
            ledgerTotal: $ledgerTotal,
            ledgers: $ledgers,
            transactions: $transactions,
            data: [
                'ledgers' => $ledgerData,
                'ledger_total' => $ledgerTotalData,
                'transactions' => $transactionData,
            ],
        );
    }

    private function accountPath(string $accountId): string
    {
        if ($accountId === '') {
            throw new InvalidArgumentException('Switch account identifier is required.');
        }

        return sprintf('accounts/%s', rawurlencode($accountId));
    }

    /** @return array{bool, array<int|string, mixed>} */
    private function optionalData(string $path, string $resource): array
    {
        try {
            $payload = $this->client->request('GET', $path);
        } catch (SwitchRequestException $exception) {
            if ($exception->statusCode === 404) {
                return [false, []];
            }

            throw $exception;
        }

        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            throw new InvalidSwitchPayloadException("Switch {$resource} response data must be an array or object.");
        }

        return [true, $data];
    }

    /** @param array<int|string, mixed> $data @return list<LedgerSummarySnapshot> */
    private function mapLedgers(array $data): array
    {
        $ledgers = [];
        foreach ($data as $sourceService => $ledger) {
            if (! is_string($sourceService) || ! is_array($ledger)) {
                throw new InvalidSwitchPayloadException('Switch ledger summary entries must be keyed objects.');
            }

            $ledgers[] = new LedgerSummarySnapshot($sourceService, $ledger);
        }

        return $ledgers;
    }

    /** @param array<int|string, mixed> $data @return list<TransactionSnapshot> */
    private function mapTransactions(array $data): array
    {
        if (! array_is_list($data)) {
            throw new InvalidSwitchPayloadException('Switch transactions response data must be a list.');
        }

        return array_map(
            fn (mixed $transaction): TransactionSnapshot => is_array($transaction)
                ? new TransactionSnapshot($transaction)
                : throw new InvalidSwitchPayloadException('Switch transaction entries must be objects.'),
            $data,
        );
    }
}
