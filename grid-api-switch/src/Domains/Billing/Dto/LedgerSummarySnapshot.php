<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Billing\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Shared\Support\DecimalString;

final readonly class LedgerSummarySnapshot
{
    public string $amount;

    public ?string $usageQuantity;

    public ?string $usageType;

    public ?string $usageUnit;

    /** @param array<string, mixed> $data */
    public function __construct(public string $sourceService, public array $data)
    {
        if ($sourceService === '') {
            throw new InvalidSwitchPayloadException('Switch ledger source service is required.');
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $this->amount = DecimalString::fromMixed($data['amount'] ?? null, 'ledger amount');
        $this->usageQuantity = DecimalString::nullable($usage['quantity'] ?? null, 'ledger usage quantity');
        $this->usageType = $this->string($usage['type'] ?? null);
        $this->usageUnit = $this->string($usage['unit'] ?? null);
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
