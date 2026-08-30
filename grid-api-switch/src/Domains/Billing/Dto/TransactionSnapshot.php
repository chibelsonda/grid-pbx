<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Billing\Dto;

use GridPbx\Switch\Shared\Exceptions\InvalidSwitchPayloadException;
use GridPbx\Switch\Shared\Support\DecimalString;

final readonly class TransactionSnapshot
{
    public string $id;

    public string $amount;

    public ?string $type;

    public ?string $reason;

    public ?string $description;

    public ?int $createdGregorian;

    public ?int $code;

    public ?int $version;

    /** @param array<string, mixed> $data */
    public function __construct(public array $data)
    {
        $id = $data['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new InvalidSwitchPayloadException('Switch transaction id is required.');
        }

        $this->id = $id;
        $this->amount = DecimalString::fromMixed($data['amount'] ?? null, 'transaction amount');
        $this->type = $this->string($data['type'] ?? null);
        $this->reason = $this->string($data['reason'] ?? null);
        $this->description = $this->string($data['description'] ?? null);
        $this->createdGregorian = is_numeric($data['created'] ?? null) ? (int) $data['created'] : null;
        $this->code = is_numeric($data['code'] ?? null) ? (int) $data['code'] : null;
        $this->version = is_numeric($data['version'] ?? null) ? (int) $data['version'] : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
