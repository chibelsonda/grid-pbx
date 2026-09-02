<?php

namespace App\Console\Commands;

use App\Support\Http\ApiContractInventory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;

#[Signature('api:contract
    {--domain=* : Restrict the inventory to one or more domain names}
    {--json : Print the complete machine-readable contract}
    {--write= : Write the JSON contract to a path relative to grid-api}')]
#[Description('Export the live GridPBX API request and response contract inventory')]
class ExportApiContractCommand extends Command
{
    /** @throws JsonException */
    public function handle(ApiContractInventory $inventory): int
    {
        $contract = $inventory->build($this->option('domain'));
        $json = json_encode(
            $contract,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $writePath = $this->option('write');

        if (is_string($writePath) && trim($writePath) !== '') {
            $path = base_path(trim($writePath));
            $directory = dirname($path);

            if (! is_dir($directory) || ! is_writable($directory)) {
                $this->error('The contract destination directory is not writable.');

                return self::FAILURE;
            }

            if (file_put_contents($path, $json.PHP_EOL) === false) {
                $this->error('The API contract could not be written.');

                return self::FAILURE;
            }

            $this->info('API contract written to '.$path);
        }

        if ($this->option('json')) {
            $this->line($json);
        } elseif (! is_string($writePath) || trim($writePath) === '') {
            $this->table(['Operations', 'Inspection errors'], [[
                data_get($contract, 'scope.operation_count'),
                data_get($contract, 'scope.inspection_error_count'),
            ]]);
        }

        return data_get($contract, 'scope.inspection_error_count') === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
