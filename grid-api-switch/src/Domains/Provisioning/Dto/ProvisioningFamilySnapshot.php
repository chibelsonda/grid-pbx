<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Provisioning\Dto;

final readonly class ProvisioningFamilySnapshot
{
    /** @param list<ProvisioningModelSnapshot> $models */
    public function __construct(
        public string $id,
        public string $name,
        public array $models,
    ) {}
}
