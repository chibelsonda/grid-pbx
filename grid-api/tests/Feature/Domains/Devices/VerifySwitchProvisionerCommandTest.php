<?php

namespace Tests\Feature\Domains\Devices;

use App\Domains\Devices\Contracts\SwitchProvisioningCatalogGateway;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class VerifySwitchProvisionerCommandTest extends TestCase
{
    public function test_it_reports_authenticated_catalog_mapping_counts_without_credentials(): void
    {
        $this->mock(SwitchProvisioningCatalogGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('catalog')->once()->andReturn([
                'available' => true,
                'reason' => null,
                'brands' => [[
                    'id' => 'yealink',
                    'name' => 'Yealink',
                    'families' => [[
                        'id' => 't5',
                        'name' => 'T5',
                        'models' => [[
                            'id' => 't54w',
                            'name' => 'T54W',
                            'template_id' => 'yealink_t5_t54w',
                        ]],
                    ]],
                ]],
            ]);
        });

        $exitCode = Artisan::call('switch:provisioner:verify', ['--json' => true]);
        $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($output['ok']);
        $this->assertSame(1, $output['brands']);
        $this->assertSame(1, $output['families']);
        $this->assertSame(1, $output['models']);
        $this->assertSame(1, $output['models_with_template_ids']);
        $this->assertSame('yealink_t5_t54w', $output['first_mapping']['template_id']);
        $this->assertStringNotContainsString('token', Artisan::output());
        $this->assertStringNotContainsString('password', Artisan::output());
    }

    public function test_it_fails_when_the_configured_catalog_is_unavailable(): void
    {
        $this->mock(SwitchProvisioningCatalogGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('catalog')->once()->andReturn([
                'available' => false,
                'reason' => 'The configured provisioning catalog is currently unavailable.',
                'brands' => [],
            ]);
        });

        $exitCode = Artisan::call('switch:provisioner:verify', ['--json' => true]);
        $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertFalse($output['ok']);
        $this->assertSame(
            'The configured provisioning catalog is currently unavailable.',
            $output['message'],
        );
    }
}
