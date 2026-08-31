<?php

namespace App\Domains\Billing\Dto;

final readonly class BillingInvoice
{
    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    public function __construct(
        public string $id,
        public ?string $number,
        public string $status,
        public ?string $currency,
        public string $total,
        public string $amountPaid,
        public string $amountDue,
        public ?string $issuedAt,
        public ?string $dueAt,
        public bool $authoritative,
        public string $source,
        public bool $documentAvailable,
        public ?string $documentContentType = null,
        public bool $lineItemsAvailable = false,
        public array $lineItems = [],
    ) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'currency' => $this->currency,
            'total' => $this->total,
            'amount_paid' => $this->amountPaid,
            'amount_due' => $this->amountDue,
            'issued_at' => $this->issuedAt,
            'due_at' => $this->dueAt,
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
            'line_items' => [
                'available' => $this->lineItemsAvailable,
                'items' => $this->lineItems,
            ],
            'document' => [
                'available' => $this->documentAvailable,
                'content_type' => $this->documentAvailable ? $this->documentContentType : null,
            ],
        ];
    }
}
