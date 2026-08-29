<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Provisioning\Dto;

final readonly class ProvisioningBrandSnapshot
{
    /** @param list<ProvisioningFamilySnapshot> $families */
    public function __construct(
        public string $id,
        public string $name,
        public array $families,
    ) {}
}
