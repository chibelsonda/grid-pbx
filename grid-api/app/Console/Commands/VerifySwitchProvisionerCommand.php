<?php

namespace App\Console\Commands;

use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;
use Illuminate\Console\Command;

class VerifySwitchProvisionerCommand extends Command
{
    protected $signature = 'switch:provisioner:verify {--json : Return machine-readable output}';

    protected $description = 'Verify authenticated connectivity and catalog mapping for the configured provisioner';

    public function handle(SwitchProvisioningCatalogGateway $catalogGateway): int
    {
        $catalog = $catalogGateway->catalog();

        if (! $catalog['available']) {
            return $this->failure($catalog['reason'] ?? 'Provisioner catalog is unavailable.');
        }

        $summary = $this->summary($catalog['brands']);

        if ($summary['brands'] === 0 || $summary['families'] === 0 || $summary['models'] === 0) {
            return $this->failure('Provisioner authentication succeeded, but its catalog is empty.');
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => true,
                ...$summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Provisioner catalog verification succeeded.');
        $this->table(
            ['Brands', 'Families', 'Models', 'Models with template IDs'],
            [[
                $summary['brands'],
                $summary['families'],
                $summary['models'],
                $summary['models_with_template_ids'],
            ]],
        );
        $this->line(sprintf(
            'First mapping: %s / %s / %s%s',
            $summary['first_mapping']['brand'],
            $summary['first_mapping']['family'],
            $summary['first_mapping']['model'],
            $summary['first_mapping']['template_id'] === null
                ? ''
                : ' -> '.$summary['first_mapping']['template_id'],
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<array{id: string, name: string, families: list<array{id: string, name: string, models: list<array{id: string, name: string, template_id: string|null}>}>}>  $brands
     * @return array{
     *     brands: int,
     *     families: int,
     *     models: int,
     *     models_with_template_ids: int,
     *     first_mapping: array{brand: string, family: string, model: string, template_id: string|null}
     * }
     */
    private function summary(array $brands): array
    {
        $families = 0;
        $models = 0;
        $modelsWithTemplateIds = 0;
        $firstMapping = null;

        foreach ($brands as $brand) {
            $families += count($brand['families']);

            foreach ($brand['families'] as $family) {
                $models += count($family['models']);

                foreach ($family['models'] as $model) {
                    if ($model['template_id'] !== null) {
                        $modelsWithTemplateIds++;
                    }

                    $firstMapping ??= [
                        'brand' => $brand['id'],
                        'family' => $family['id'],
                        'model' => $model['id'],
                        'template_id' => $model['template_id'],
                    ];
                }
            }
        }

        return [
            'brands' => count($brands),
            'families' => $families,
            'models' => $models,
            'models_with_template_ids' => $modelsWithTemplateIds,
            'first_mapping' => $firstMapping ?? [
                'brand' => '',
                'family' => '',
                'model' => '',
                'template_id' => null,
            ],
        ];
    }

    private function failure(string $message): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => false,
                'message' => $message,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
