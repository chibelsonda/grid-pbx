<?php

namespace App\Domains\Services\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Models\SwitchServiceLimit;
use App\Domains\Services\Models\SwitchServicePlan;
use App\Domains\Services\Models\SwitchServiceQuantity;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Services\RedactSensitiveSwitchData;
use Carbon\CarbonImmutable;
use UnexpectedValueException;

class ServiceProjectionService
{
    private const GREGORIAN_UNIX_OFFSET = 62167219200;

    public function __construct(private readonly RedactSensitiveSwitchData $redactor) {}

    /** @param array<string, mixed> $snapshot */
    public function project(SwitchAccount $account, array $snapshot): SwitchServiceSummary
    {
        $summaryData = $snapshot['summary'] ?? null;
        $limitsData = $snapshot['limits'] ?? null;
        $plans = $snapshot['plans'] ?? null;
        $quantities = $snapshot['quantities'] ?? null;
        if (! is_array($summaryData) || ! is_array($limitsData) || ! is_array($plans) || ! is_array($quantities)) {
            throw new UnexpectedValueException('Switch service snapshot is incomplete.');
        }

        $summary = SwitchServiceSummary::query()->firstOrNew(['switch_account_id' => $account->getKey()]);
        $next = $summaryData['billing_cycle_next_gregorian'] ?? null;
        $summary->fill([
            'status_acceptable' => (bool) ($summaryData['acceptable'] ?? false), 'status_reason' => $this->string($summaryData['status_reason'] ?? null),
            'is_reseller' => (bool) ($summaryData['is_reseller'] ?? false), 'billing_cycle_period' => max(0, (int) ($summaryData['billing_cycle_period'] ?? 0)),
            'billing_cycle_unit' => $this->string($summaryData['billing_cycle_unit'] ?? null),
            'billing_cycle_next_at' => is_int($next) && $next >= self::GREGORIAN_UNIX_OFFSET ? CarbonImmutable::createFromTimestampUTC($next - self::GREGORIAN_UNIX_OFFSET) : null,
            'assigned_plan_count' => count($plans), 'invoice_count' => max(0, (int) ($summaryData['invoice_count'] ?? 0)),
            'due_today' => (float) ($summaryData['due_today'] ?? 0), 'recurring_amount' => (float) ($summaryData['recurring_amount'] ?? 0),
            'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $summary->exists ? $summary->projection_version + 1 : 1,
            'switch_json' => $this->redactor->handle(is_array($summaryData['data'] ?? null) ? $summaryData['data'] : []),
        ]);
        $summary->save();

        $limits = SwitchServiceLimit::query()->firstOrNew(['switch_account_id' => $account->getKey()]);
        $limits->fill([
            'enabled' => (bool) ($limitsData['enabled'] ?? true), 'allow_prepay' => (bool) ($limitsData['allow_prepay'] ?? true), 'allow_postpay' => (bool) ($limitsData['allow_postpay'] ?? false),
            'inbound_trunks' => max(0, (int) ($limitsData['inbound_trunks'] ?? 0)), 'outbound_trunks' => max(0, (int) ($limitsData['outbound_trunks'] ?? 0)),
            'twoway_trunks' => max(0, (int) ($limitsData['twoway_trunks'] ?? 0)), 'burst_trunks' => max(0, (int) ($limitsData['burst_trunks'] ?? 0)),
            'calls' => is_int($limitsData['calls'] ?? null) ? max(0, $limitsData['calls']) : null,
            'resource_consuming_calls' => is_int($limitsData['resource_consuming_calls'] ?? null) ? max(0, $limitsData['resource_consuming_calls']) : null,
            'soft_limit_inbound' => (bool) ($limitsData['soft_limit_inbound'] ?? false), 'soft_limit_outbound' => (bool) ($limitsData['soft_limit_outbound'] ?? false),
            'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy,
            'projection_version' => $limits->exists ? $limits->projection_version + 1 : 1,
            'switch_json' => $this->redactor->handle(is_array($limitsData['data'] ?? null) ? $limitsData['data'] : []),
        ]);
        $limits->save();

        $seenPlans = [];
        foreach ($plans as $planData) {
            if (! is_array($planData) || ($resourceId = $this->string($planData['switch_resource_id'] ?? null)) === null) {
                continue;
            }
            $seenPlans[] = $resourceId;
            $plan = SwitchServicePlan::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'switch_resource_id' => $resourceId]);
            $plan->fill(['name' => $this->string($planData['name'] ?? null), 'description' => $this->string($planData['description'] ?? null), 'category' => $this->string($planData['category'] ?? null), 'last_synced_at' => now(), 'sync_status' => ProjectionStatus::Healthy, 'projection_version' => $plan->exists ? $plan->projection_version + 1 : 1, 'switch_json' => $this->redactor->handle(is_array($planData['data'] ?? null) ? $planData['data'] : [])]);
            $plan->deleted_at = null;
            $plan->save();
        }
        $missingPlans = SwitchServicePlan::query()->where('switch_account_id', $account->getKey())->when($seenPlans !== [], fn ($query) => $query->whereNotIn('switch_resource_id', $seenPlans))->get();
        SwitchServicePlan::destroy($missingPlans->modelKeys());

        $seenQuantities = [];
        foreach ($quantities as $quantityData) {
            if (! is_array($quantityData)) {
                continue;
            }
            $scope = $this->string($quantityData['scope'] ?? null);
            $category = $this->string($quantityData['category'] ?? null);
            $item = $this->string($quantityData['item'] ?? null);
            if ($scope === null || $category === null || $item === null || ! in_array($scope, ['account', 'cascade', 'manual'], true)) {
                continue;
            }
            $seenQuantities[] = "{$scope}\0{$category}\0{$item}";
            $quantity = SwitchServiceQuantity::withTrashed()->firstOrNew(['switch_account_id' => $account->getKey(), 'scope' => $scope, 'category' => $category, 'item' => $item]);
            $quantity->fill(['quantity' => (float) ($quantityData['quantity'] ?? 0), 'last_synced_at' => now()]);
            $quantity->deleted_at = null;
            $quantity->save();
        }
        $missingQuantities = SwitchServiceQuantity::query()->where('switch_account_id', $account->getKey())->get()->filter(fn (SwitchServiceQuantity $quantity): bool => ! in_array("{$quantity->scope}\0{$quantity->category}\0{$quantity->item}", $seenQuantities, true));
        SwitchServiceQuantity::destroy($missingQuantities->modelKeys());

        return $summary->load(['plans', 'quantities']);
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
