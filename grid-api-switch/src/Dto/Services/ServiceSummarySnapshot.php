<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Services;

use GridPbx\Switch\Exceptions\InvalidSwitchPayloadException;

final readonly class ServiceSummarySnapshot
{
    /** @var list<ServicePlanSnapshot> */
    public array $plans;

    /** @var list<ServiceQuantitySnapshot> */
    public array $quantities;

    public bool $acceptable;

    public ?string $statusReason;

    public bool $isReseller;

    public ?string $resellerId;

    public ?int $billingCycleNextGregorian;

    public int $billingCyclePeriod;

    public ?string $billingCycleUnit;

    public int $invoiceCount;

    public float $dueToday;

    public float $recurringAmount;

    /** @param array<string, mixed> $data */
    public function __construct(public array $data)
    {
        $plans = $data['plans'] ?? [];
        $quantities = $data['quantities'] ?? [];
        if (! is_array($plans) || ! is_array($quantities)) {
            throw new InvalidSwitchPayloadException('Switch service summary contains invalid plans or quantities.');
        }

        $this->plans = $this->mapPlans($plans);
        $this->quantities = $this->mapQuantities($quantities);
        $status = is_array($data['status'] ?? null) ? $data['status'] : [];
        $reseller = is_array($data['reseller'] ?? null) ? $data['reseller'] : [];
        $cycle = is_array($data['billing_cycle'] ?? null) ? $data['billing_cycle'] : [];
        $invoices = is_array($data['invoices'] ?? null) ? $data['invoices'] : [];
        $this->acceptable = (bool) ($status['acceptable'] ?? false);
        $this->statusReason = $this->string($status['reason'] ?? null);
        $this->isReseller = (bool) ($reseller['is_reseller'] ?? false);
        $this->resellerId = $this->string($reseller['id'] ?? null);
        $this->billingCycleNextGregorian = is_numeric($cycle['next'] ?? null) ? (int) $cycle['next'] : null;
        $this->billingCyclePeriod = max(0, (int) ($cycle['period'] ?? 0));
        $this->billingCycleUnit = $this->string($cycle['unit'] ?? null);
        $this->invoiceCount = count($invoices);
        [$this->dueToday, $this->recurringAmount] = $this->invoiceTotals($invoices);
    }

    /** @param array<string, mixed> $plans @return list<ServicePlanSnapshot> */
    private function mapPlans(array $plans): array
    {
        $mapped = [];
        foreach ($plans as $id => $value) {
            if (! is_string($id) || $id === '') {
                continue;
            }
            $plan = is_array($value) ? $value : [];
            $mapped[] = new ServicePlanSnapshot($id, $this->string($plan['name'] ?? null), $this->string($plan['description'] ?? null), $this->string($plan['category'] ?? null), $plan);
        }

        return $mapped;
    }

    /** @param array<string, mixed> $quantities @return list<ServiceQuantitySnapshot> */
    private function mapQuantities(array $quantities): array
    {
        $mapped = [];
        foreach (['account', 'cascade', 'manual'] as $scope) {
            $categories = is_array($quantities[$scope] ?? null) ? $quantities[$scope] : [];
            foreach ($categories as $category => $items) {
                if (! is_string($category) || ! is_array($items)) {
                    continue;
                }
                foreach ($items as $item => $quantity) {
                    if (is_string($item) && is_numeric($quantity)) {
                        $mapped[] = new ServiceQuantitySnapshot($scope, $category, $item, (float) $quantity);
                    }
                }
            }
        }

        return $mapped;
    }

    /** @param array<int|string, mixed> $invoices @return array{float, float} */
    private function invoiceTotals(array $invoices): array
    {
        $today = 0.0;
        $recurring = 0.0;
        foreach ($invoices as $invoice) {
            $summary = is_array($invoice) && is_array($invoice['summary'] ?? null) ? $invoice['summary'] : [];
            if (is_numeric($summary['today'] ?? null)) {
                $today += (float) $summary['today'];
            }
            if (is_numeric($summary['recurring'] ?? null)) {
                $recurring += (float) $summary['recurring'];
            }
        }

        return [$today, $recurring];
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
