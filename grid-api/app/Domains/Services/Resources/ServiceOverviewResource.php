<?php

namespace App\Domains\Services\Resources;

use App\Domains\Services\Models\SwitchServiceSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SwitchServiceSummary */
class ServiceOverviewResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $reconciliation
     * @param  array<string, mixed>  $documents
     */
    public function __construct(
        SwitchServiceSummary $resource,
        private readonly array $reconciliation,
        private readonly array $documents,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $account = $this->switchAccount;

        return [
            'id' => $this->id,
            'standing' => ['acceptable' => $this->status_acceptable, 'reason' => $this->status_reason],
            'reseller' => [
                'is_reseller' => $this->is_reseller,
                'billing_account' => $this->billingResellerAccount === null ? null : [
                    'id' => $this->billingResellerAccount->id,
                    'name' => $this->billingResellerAccount->name,
                    'realm' => $this->billingResellerAccount->realm,
                ],
                'billing_account_projected' => $this->billing_reseller_switch_account_id === null
                    || $this->billingResellerAccount !== null,
            ],
            'billing_cycle' => ['next_at' => $this->billing_cycle_next_at?->toIso8601String(), 'period' => $this->billing_cycle_period, 'unit' => $this->billing_cycle_unit],
            'billing_impact' => ['invoice_count' => $this->invoice_count, 'due_today' => (float) $this->due_today, 'recurring_amount' => (float) $this->recurring_amount],
            'billing' => $account?->billingSummary === null ? null : [
                'id' => $account->billingSummary->id,
                'ledger_total' => $account->billingSummary->ledger_total,
                'ledger_source_count' => $account->billingSummary->ledger_source_count,
                'transaction_count' => $account->billingSummary->transaction_count,
                'availability' => [
                    'ledgers' => $account->billingSummary->ledgers_available,
                    'ledger_total' => $account->billingSummary->ledger_total_available,
                    'transactions' => $account->billingSummary->transactions_available,
                ],
                'ledger_summaries' => $account->ledgerSummaries->map(fn ($ledger) => [
                    'id' => $ledger->id,
                    'source_service' => $ledger->source_service,
                    'amount' => $ledger->amount,
                    'usage_quantity' => $ledger->usage_quantity,
                    'usage_type' => $ledger->usage_type,
                    'usage_unit' => $ledger->usage_unit,
                ])->values(),
                'transactions' => $account->billingTransactions->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'type' => $transaction->type,
                    'reason' => $transaction->reason,
                    'description' => $transaction->description,
                    'code' => $transaction->code,
                    'created_at' => $transaction->switch_created_at?->toIso8601String(),
                ])->values(),
                'last_synced_at' => $account->billingSummary->last_synced_at?->toIso8601String(),
            ],
            'reconciliation' => $this->reconciliation,
            'documents' => $this->documents,
            'plans' => $this->whenLoaded('plans', fn () => $this->plans->map(fn ($plan) => ['id' => $plan->id, 'name' => $plan->name, 'description' => $plan->description, 'category' => $plan->category])->values()),
            'quantities' => $this->whenLoaded('quantities', fn () => $this->quantities->map(fn ($quantity) => ['id' => $quantity->id, 'scope' => $quantity->scope, 'category' => $quantity->category, 'item' => $quantity->item, 'quantity' => (float) $quantity->quantity])->values()),
            'limits' => $account?->serviceLimit === null ? null : ['id' => $account->serviceLimit->id, 'enabled' => $account->serviceLimit->enabled, 'allow_prepay' => $account->serviceLimit->allow_prepay, 'allow_postpay' => $account->serviceLimit->allow_postpay, 'inbound_trunks' => $account->serviceLimit->inbound_trunks, 'outbound_trunks' => $account->serviceLimit->outbound_trunks, 'twoway_trunks' => $account->serviceLimit->twoway_trunks, 'burst_trunks' => $account->serviceLimit->burst_trunks, 'calls' => $account->serviceLimit->calls, 'resource_consuming_calls' => $account->serviceLimit->resource_consuming_calls, 'soft_limit_inbound' => $account->serviceLimit->soft_limit_inbound, 'soft_limit_outbound' => $account->serviceLimit->soft_limit_outbound],
            'last_synced_at' => $this->last_synced_at?->toIso8601String(), 'sync_status' => $this->sync_status?->value,
        ];
    }
}
