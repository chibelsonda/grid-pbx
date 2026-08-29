<?php

declare(strict_types=1);

namespace GridPbx\Switch\Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use PHPUnit\Framework\TestCase;

final class ArchitectureTest extends TestCase
{
    public function test_legacy_flat_source_directories_contain_no_php_classes(): void
    {
        foreach (['Contracts', 'Dto', 'Exceptions', 'Http', 'Provisioning', 'Resources'] as $directory) {
            self::assertSame([], $this->phpFiles(dirname(__DIR__, 2).'/src/'.$directory));
        }
    }

    public function test_the_switch_client_has_no_framework_or_database_dependency(): void
    {
        $composer = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $dependencies = array_keys($composer['require'] ?? []);

        foreach ($dependencies as $dependency) {
            self::assertFalse(str_starts_with($dependency, 'laravel/'));
            self::assertFalse(str_starts_with($dependency, 'illuminate/'));
            self::assertNotSame('doctrine/dbal', $dependency);
        }
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
