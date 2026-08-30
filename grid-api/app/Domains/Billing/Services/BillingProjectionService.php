<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\SwitchBillingSummary;
use App\Domains\Billing\Models\SwitchBillingTransaction;
use App\Domains\Billing\Models\SwitchLedgerSummary;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Carbon\CarbonImmutable;
use UnexpectedValueException;

class BillingProjectionService
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;

    public function __construct(private readonly RedactSensitiveSwitchData $redactor) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchBillingSummary
    {
        $ledgersAvailable = $snapshot['ledgers_available'] ?? null;
        $ledgerTotalAvailable = $snapshot['ledger_total_available'] ?? null;
        $transactionsAvailable = $snapshot['transactions_available'] ?? null;
        $ledgers = $snapshot['ledgers'] ?? null;
        $transactions = $snapshot['transactions'] ?? null;

        if (! is_bool($ledgersAvailable)
            || ! is_bool($ledgerTotalAvailable)
            || ! is_bool($transactionsAvailable)
            || ! is_array($ledgers)
            || ! is_array($transactions)) {
            throw new UnexpectedValueException('Switch billing snapshot is incomplete.');
        }

        if ($ledgersAvailable) {
            $this->projectLedgers($account, $ledgers);
        } else {
            $account->ledgerSummaries()->update(['sync_status' => ProjectionStatus::Stale->value]);
        }

        if ($transactionsAvailable) {
            $this->projectTransactions($account, $transactions);
        } else {
            $account->billingTransactions()->update(['sync_status' => ProjectionStatus::Stale->value]);
        }

        $summary = SwitchBillingSummary::query()->firstOrNew([
            'switch_account_id' => $account->getKey(),
        ]);
        $summary->fill([
            'ledger_total' => $ledgerTotalAvailable
                ? ($snapshot['ledger_total'] ?? null)
                : $summary->ledger_total,
            'ledger_source_count' => $account->ledgerSummaries()->count(),
            'transaction_count' => $account->billingTransactions()->count(),
            'ledgers_available' => $ledgersAvailable,
            'ledger_total_available' => $ledgerTotalAvailable,
            'transactions_available' => $transactionsAvailable,
            'last_synced_at' => now(),
            'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $summary->exists ? $summary->projection_version + 1 : 1,
            'switch_json' => $this->redactor->handle(
                is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [],
            ),
        ]);
        $summary->save();

        return $summary;
    }

    /** @param array<int, mixed> $ledgers */
    private function projectLedgers(SwitchAccount $account, array $ledgers): void
    {
        $seen = [];

        foreach ($ledgers as $data) {
            if (! is_array($data) || ($sourceService = $this->string($data['source_service'] ?? null)) === null) {
                throw new UnexpectedValueException('Switch ledger summary entry is invalid.');
            }

            $seen[] = $sourceService;
            $ledger = SwitchLedgerSummary::withTrashed()->firstOrNew([
                'switch_account_id' => $account->getKey(),
                'source_service' => $sourceService,
            ]);
            $ledger->fill([
                'amount' => $data['amount'] ?? '0',
                'usage_quantity' => $data['usage_quantity'] ?? null,
                'usage_type' => $this->string($data['usage_type'] ?? null),
                'usage_unit' => $this->string($data['usage_unit'] ?? null),
                'last_synced_at' => now(),
                'sync_status' => ProjectionStatus::Healthy,
                'projection_version' => $ledger->exists ? $ledger->projection_version + 1 : 1,
                'switch_json' => $this->redactor->handle(
                    is_array($data['data'] ?? null) ? $data['data'] : [],
                ),
            ]);
            $ledger->deleted_at = null;
            $ledger->save();
        }

        $missing = SwitchLedgerSummary::query()
            ->where('switch_account_id', $account->getKey())
            ->when($seen !== [], fn ($query) => $query->whereNotIn('source_service', $seen))
            ->get();
        SwitchLedgerSummary::destroy($missing->modelKeys());
    }

    /** @param array<int, mixed> $transactions */
    private function projectTransactions(SwitchAccount $account, array $transactions): void
    {
        foreach ($transactions as $data) {
            if (! is_array($data) || ($resourceId = $this->string($data['switch_resource_id'] ?? null)) === null) {
                throw new UnexpectedValueException('Switch billing transaction entry is invalid.');
            }

            $transaction = SwitchBillingTransaction::withTrashed()->firstOrNew([
                'switch_account_id' => $account->getKey(),
                'switch_resource_id' => $resourceId,
            ]);
            $transaction->fill([
                'amount' => $data['amount'] ?? '0',
                'type' => $this->string($data['type'] ?? null),
                'reason' => $this->string($data['reason'] ?? null),
                'description' => $this->string($data['description'] ?? null),
                'code' => is_int($data['code'] ?? null) ? $data['code'] : null,
                'switch_version' => is_int($data['version'] ?? null) ? $data['version'] : null,
                'switch_created_at' => $this->gregorianTimestamp($data['created_gregorian'] ?? null),
                'last_synced_at' => now(),
                'sync_status' => ProjectionStatus::Healthy,
                'projection_version' => $transaction->exists ? $transaction->projection_version + 1 : 1,
                'switch_json' => $this->redactor->handle(
                    is_array($data['data'] ?? null) ? $data['data'] : [],
                ),
            ]);
            $transaction->deleted_at = null;
            $transaction->save();
        }
    }

    private function gregorianTimestamp(mixed $value): ?CarbonImmutable
    {
        return is_int($value) && $value >= self::GREGORIAN_UNIX_OFFSET
            ? CarbonImmutable::createFromTimestampUTC($value - self::GREGORIAN_UNIX_OFFSET)
            : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
