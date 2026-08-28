<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Services;

final readonly class ServiceQuantitySnapshot
{
    public function __construct(
        public string $scope,
        public string $category,
        public string $item,
        public float $quantity,
    ) {}
}
