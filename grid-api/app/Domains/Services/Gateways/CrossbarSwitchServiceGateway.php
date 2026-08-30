<?php

namespace App\Domains\Services\Gateways;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Contracts\SwitchServiceGateway;
use GridPbx\Switch\Domains\Billing\BillingResourceClient;
use GridPbx\Switch\Domains\Services\ServiceResourceClient;

class CrossbarSwitchServiceGateway implements SwitchServiceGateway
{
    public function __construct(
        private readonly ServiceResourceClient $services,
        private readonly BillingResourceClient $billing,
    ) {}

    public function snapshot(SwitchAccount $account): array
    {
        $summary = $this->services->summary($account->switch_account_id);
        $limits = $this->services->limits($account->switch_account_id);
        $billing = $this->billing->snapshot($account->switch_account_id);

        return [
            'summary' => ['acceptable' => $summary->acceptable, 'status_reason' => $summary->statusReason, 'is_reseller' => $summary->isReseller, 'reseller_id' => $summary->resellerId, 'billing_cycle_next_gregorian' => $summary->billingCycleNextGregorian, 'billing_cycle_period' => $summary->billingCyclePeriod, 'billing_cycle_unit' => $summary->billingCycleUnit, 'invoice_count' => $summary->invoiceCount, 'due_today' => $summary->dueToday, 'recurring_amount' => $summary->recurringAmount, 'data' => $summary->data],
            'plans' => array_map(fn ($plan) => ['switch_resource_id' => $plan->id, 'name' => $plan->name, 'description' => $plan->description, 'category' => $plan->category, 'data' => $plan->data], $summary->plans),
            'quantities' => array_map(fn ($quantity) => ['scope' => $quantity->scope, 'category' => $quantity->category, 'item' => $quantity->item, 'quantity' => $quantity->quantity], $summary->quantities),
            'limits' => ['enabled' => $limits->enabled, 'allow_prepay' => $limits->allowPrepay, 'allow_postpay' => $limits->allowPostpay, 'inbound_trunks' => $limits->inboundTrunks, 'outbound_trunks' => $limits->outboundTrunks, 'twoway_trunks' => $limits->twowayTrunks, 'burst_trunks' => $limits->burstTrunks, 'calls' => $limits->calls, 'resource_consuming_calls' => $limits->resourceConsumingCalls, 'soft_limit_inbound' => $limits->softLimitInbound, 'soft_limit_outbound' => $limits->softLimitOutbound, 'data' => $limits->toArray()],
            'billing' => [
                'ledgers_available' => $billing->ledgersAvailable,
                'ledger_total_available' => $billing->ledgerTotalAvailable,
                'transactions_available' => $billing->transactionsAvailable,
                'ledger_total' => $billing->ledgerTotal,
                'ledgers' => array_map(fn ($ledger) => [
                    'source_service' => $ledger->sourceService,
                    'amount' => $ledger->amount,
                    'usage_quantity' => $ledger->usageQuantity,
                    'usage_type' => $ledger->usageType,
                    'usage_unit' => $ledger->usageUnit,
                    'data' => $ledger->data,
                ], $billing->ledgers),
                'transactions' => array_map(fn ($transaction) => [
                    'switch_resource_id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'type' => $transaction->type,
                    'reason' => $transaction->reason,
                    'description' => $transaction->description,
                    'created_gregorian' => $transaction->createdGregorian,
                    'code' => $transaction->code,
                    'version' => $transaction->version,
                    'data' => $transaction->data,
                ], $billing->transactions),
                'data' => $billing->data,
            ],
        ];
    }
}
