<?php

namespace App\Domains\Billing\Dto;

use Closure;

final readonly class BillingDocumentContent
{
    /** @param  Closure(): void  $stream */
    public function __construct(
        public string $contentType,
        public int $contentLength,
        public Closure $stream,
    ) {}
}
