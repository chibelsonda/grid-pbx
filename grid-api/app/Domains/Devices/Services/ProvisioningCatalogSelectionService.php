<?php

namespace App\Domains\Devices\Services;

use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;

class ProvisioningCatalogSelectionService
{
    public function __construct(
        private readonly SwitchProvisioningCatalogGateway $catalogGateway,
    ) {}

    /**
     * @return array<string, string>
     */
    public function errors(
        mixed $brandValue,
        mixed $familyValue,
        mixed $modelValue,
        mixed $templateValue,
    ): array {
        $catalog = $this->catalogGateway->catalog();

        if (! ($catalog['available'] ?? false)) {
            return [];
        }

        $brand = $this->findByIdentity((array) ($catalog['brands'] ?? []), $brandValue);

        if ($brand === null) {
            return ['provision.endpoint_brand' => 'Select a brand from the current provisioning catalog.'];
        }

        $family = $this->findByIdentity((array) ($brand['families'] ?? []), $familyValue);

        if ($family === null) {
            return ['provision.endpoint_family' => 'Select a family belonging to the selected brand.'];
        }

        $selectedModels = is_array($modelValue) ? $modelValue : [$modelValue];
        $models = [];

        foreach ($selectedModels as $selectedModel) {
            $model = $this->findByIdentity(
                (array) ($family['models'] ?? []),
                $selectedModel,
                includeTemplateId: true,
            );

            if ($model === null) {
                return ['provision.endpoint_model' => 'Select a model belonging to the selected brand and family.'];
            }

            $models[] = $model;
        }

        if (is_string($templateValue) && trim($templateValue) !== '') {
            $matchesTemplate = collect($models)->contains(
                fn (array $model): bool => $this->matches($templateValue, $model['template_id'] ?? null),
            );

            if (! $matchesTemplate) {
                return ['provision.id' => 'The provisioning template does not belong to the selected model.'];
            }
        }

        return [];
    }

    /** @param array<int, mixed> $items */
    private function findByIdentity(array $items, mixed $selected, bool $includeTemplateId = false): ?array
    {
        if (! is_string($selected) && ! is_int($selected)) {
            return null;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($this->matches($selected, $item['id'] ?? null, $item['name'] ?? null)) {
                return $item;
            }

            if ($includeTemplateId && $this->matches($selected, $item['template_id'] ?? null)) {
                return $item;
            }
        }

        return null;
    }

    private function matches(string|int $selected, mixed ...$candidates): bool
    {
        $selected = mb_strtolower(trim((string) $selected));

        foreach ($candidates as $candidate) {
            if ((is_string($candidate) || is_int($candidate))
                && $selected === mb_strtolower(trim((string) $candidate))) {
                return true;
            }
        }

        return false;
    }
}
