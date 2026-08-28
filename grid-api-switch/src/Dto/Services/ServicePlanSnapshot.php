<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Services;

final readonly class ServicePlanSnapshot
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $description,
        public ?string $category,
        public array $data,
    ) {}
}
