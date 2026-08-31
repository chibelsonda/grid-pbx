<?php

namespace App\Domains\Billing\Dto;

final readonly class BillingReceipt
{
    public function __construct(
        public string $id,
        public ?string $number,
        public string $status,
        public ?string $currency,
        public string $amount,
        public ?string $paidAt,
        public bool $authoritative,
        public string $source,
        public bool $documentAvailable,
        public ?string $documentContentType = null,
    ) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'paid_at' => $this->paidAt,
            'document_available' => $this->documentAvailable,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...$this->summary(),
            'authoritative' => $this->authoritative,
            'source' => $this->source,
            'document' => [
                'available' => $this->documentAvailable,
                'content_type' => $this->documentAvailable ? $this->documentContentType : null,
            ],
        ];
    }
}
