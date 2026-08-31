<?php

namespace App\Domains\Billing\Services;

use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Shared\Exceptions\SwitchAuthenticationException;
use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use UnexpectedValueException;

class BillingReconciliationService
{
    /** @return array<string, mixed> */
    public function reconcile(SwitchServiceSummary $summary): array
    {
        $account = $summary->switchAccount;
        $billing = $account->billingSummary;
        $runs = $account->syncRuns()
            ->where('resource_type', 'services')
            ->latest('sync_run_id')
            ->limit(10)
            ->get();
        $latestRun = $runs->first();
        $checks = [
            $this->latestSyncCheck($latestRun),
            $this->projectionCheck('service_projection', 'Service projection', $summary->sync_status),
            $this->projectionCheck('billing_projection', 'Billing projection', $billing?->sync_status),
        ];

        if ($billing !== null) {
            $ledgerCount = $account->ledgerSummaries()->count();
            $transactionCount = $account->billingTransactions()->count();

            $checks = [
                ...$checks,
                $this->availabilityCheck('ledger_endpoint', 'Ledger sources', $billing->ledgers_available),
                $this->availabilityCheck('ledger_total_endpoint', 'Ledger total', $billing->ledger_total_available),
                $this->availabilityCheck('transaction_endpoint', 'Transactions', $billing->transactions_available),
                $this->countCheck('ledger_projection_count', 'Ledger row count', $billing->ledger_source_count, $ledgerCount),
                $this->countCheck('transaction_projection_count', 'Transaction row count', $billing->transaction_count, $transactionCount),
            ];
        }

        $billingOwnerProjected = $summary->billing_reseller_switch_account_id === null
            || $summary->billingResellerAccount !== null;
        $checks[] = $this->check(
            'billing_owner_projection',
            'Billing owner mapping',
            $billingOwnerProjected ? 'passed' : 'warning',
            $billingOwnerProjected
                ? 'The billing owner reference is projected or is not required.'
                : 'Switch reports a billing owner that is not mapped to a managed GridPBX account.',
            $billingOwnerProjected
                ? 'No recovery action is required.'
                : 'Project or onboard the referenced billing reseller, then synchronize services again.',
        );

        $status = collect($checks)->contains('status', 'failed')
            ? 'error'
            : (collect($checks)->contains('status', 'warning') ? 'attention' : 'healthy');

        return [
            'status' => $status,
            'checks' => $checks,
            'sync_history' => $runs->map(fn (SyncRun $run): array => $this->syncRun($run))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function latestSyncCheck(?SyncRun $run): array
    {
        if ($run === null) {
            return $this->check(
                'latest_service_sync',
                'Latest service synchronization',
                'warning',
                'No service synchronization run has been recorded for this account.',
                'Run the read-only service synchronization before relying on billing projections.',
            );
        }

        return match ($run->status) {
            SyncRunStatus::Succeeded => $this->check(
                'latest_service_sync',
                'Latest service synchronization',
                'passed',
                'The latest service and billing synchronization completed successfully.',
                'No recovery action is required.',
            ),
            SyncRunStatus::Failed => $this->failedSyncCheck($run),
            default => $this->check(
                'latest_service_sync',
                'Latest service synchronization',
                'warning',
                'The latest service synchronization has not finished.',
                'Wait for the queued synchronization to finish before retrying it.',
            ),
        };
    }

    /** @return array<string, mixed> */
    private function failedSyncCheck(SyncRun $run): array
    {
        $failure = $this->safeFailure($run->error_code);

        return $this->check(
            'latest_service_sync',
            'Latest service synchronization',
            'failed',
            $failure['message'],
            $failure['guidance'],
        );
    }

    /** @return array<string, mixed> */
    private function projectionCheck(string $code, string $label, ?ProjectionStatus $status): array
    {
        if ($status === null) {
            return $this->check(
                $code,
                $label,
                'warning',
                "No {$label} has been recorded.",
                'Run the read-only service synchronization. If the projection remains unavailable, review the safe sync history below.',
            );
        }

        return match ($status) {
            ProjectionStatus::Healthy => $this->check(
                $code,
                $label,
                'passed',
                "The {$label} is healthy.",
                'No recovery action is required.',
            ),
            ProjectionStatus::Error => $this->check(
                $code,
                $label,
                'failed',
                "The {$label} could not be refreshed.",
                'Retry the read-only synchronization. If it fails again, ask an administrator to review server logs using the public run reference.',
            ),
            default => $this->check(
                $code,
                $label,
                'warning',
                "The {$label} is {$status->value}.",
                'Run or wait for the read-only service synchronization before relying on this projection.',
            ),
        };
    }

    /** @return array<string, mixed> */
    private function availabilityCheck(string $code, string $label, bool $available): array
    {
        return $this->check(
            $code,
            $label,
            $available ? 'passed' : 'warning',
            $available
                ? "The Switch exposes the read-only {$label} endpoint."
                : "The connected Switch version does not expose the read-only {$label} endpoint.",
            $available
                ? 'No recovery action is required.'
                : 'Retain existing history and verify endpoint support before upgrading or changing the Switch deployment. No write fallback is allowed.',
        );
    }

    /** @return array<string, mixed> */
    private function countCheck(string $code, string $label, int $expected, int $actual): array
    {
        $matches = $expected === $actual;

        return $this->check(
            $code,
            $label,
            $matches ? 'passed' : 'failed',
            $matches
                ? "The stored summary matches the {$actual} projected rows."
                : "The stored summary expects {$expected} rows, but {$actual} active rows are projected.",
            $matches
                ? 'No recovery action is required.'
                : 'Run the read-only synchronization again. If the mismatch remains, review the worker and database logs; do not edit projection rows manually.',
            $expected,
            $actual,
        );
    }

    /** @return array<string, mixed> */
    private function check(
        string $code,
        string $label,
        string $status,
        string $message,
        string $guidance,
        ?int $expectedCount = null,
        ?int $actualCount = null,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'guidance' => $guidance,
            'expected_count' => $expectedCount,
            'actual_count' => $actualCount,
        ];
    }

    /** @return array<string, mixed> */
    private function syncRun(SyncRun $run): array
    {
        $failure = $run->status === SyncRunStatus::Failed
            ? $this->safeFailure($run->error_code)
            : null;

        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'processed_count' => $run->processed_count,
            'failure_category' => $failure['category'] ?? null,
            'message' => $failure['message'] ?? null,
            'guidance' => $failure['guidance'] ?? null,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }

    /** @return array{category: string, message: string, guidance: string} */
    private function safeFailure(?string $exceptionClass): array
    {
        return match ($exceptionClass) {
            SwitchAuthenticationException::class => [
                'category' => 'authentication',
                'message' => 'Switch authentication prevented the billing synchronization.',
                'guidance' => 'Ask an administrator to verify the server-side Switch credentials and retry. Credentials must never be entered in the GridPBX UI.',
            ],
            SwitchRequestException::class => [
                'category' => 'switch_request',
                'message' => 'Switch could not complete the billing synchronization request.',
                'guidance' => 'Retry the read-only synchronization. If it fails again, verify Switch availability and review server logs using the public run reference.',
            ],
            InvalidSwitchPayloadException::class, UnexpectedValueException::class => [
                'category' => 'response_validation',
                'message' => 'The Switch response could not be safely projected.',
                'guidance' => 'Verify the connected Switch version and schema, then retry. Review server logs if the response remains incompatible.',
            ],
            default => [
                'category' => 'synchronization',
                'message' => 'The service and billing synchronization failed.',
                'guidance' => 'Retry the read-only synchronization. If it fails again, ask an administrator to review server logs using the public run reference.',
            ],
        };
    }
}
