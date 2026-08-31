<?php

namespace App\Domains\Billing\Dto;

final readonly class BillingDocumentSourceResult
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public bool $available,
        public bool $authoritative,
        public string $source,
        public array $items,
        public string $guidance,
        public ?int $reportedCount = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'available' => $this->available,
            'authoritative' => $this->authoritative,
            'source' => $this->source,
            'items' => $this->items,
            'guidance' => $this->guidance,
        ];

        if ($this->reportedCount !== null) {
            $data['reported_count'] = $this->reportedCount;
        }

        return $data;
    }
}
