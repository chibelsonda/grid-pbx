<?php

declare(strict_types=1);

namespace GridPbx\Switch\Domains\Provisioning\Dto;

final readonly class ProvisioningModelSnapshot
{
    /**
     * @param  list<string>  $supportedKeyTypes
     * @param  list<string>  $valueSources
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $templateId = null,
        public ?int $maxKeys = null,
        public ?int $maxExpansionModules = null,
        public ?int $keysPerExpansionModule = null,
        public array $supportedKeyTypes = [],
        public array $valueSources = [],
        public ?string $manufacturerProvider = null,
    ) {}
}
