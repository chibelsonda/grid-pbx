<?php

namespace Tests\Feature\Support;

use App\Support\Http\ApiContractInventory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

class ApiContractExportCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_exports_machine_readable_payment_request_and_response_contracts(): void
    {
        $contract = app(ApiContractInventory::class)->build(['Payments']);
        $charge = collect($contract['operations'])->firstWhere(
            'controller',
            'App\\Domains\\Payments\\Controllers\\SandboxChargeController',
        );

        $this->assertIsArray($charge);
        $this->assertSame('Payments', $charge['domain']);
        $this->assertSame('POST', $charge['method']);
        $this->assertSame(
            'App\\Domains\\Payments\\Requests\\CreateSandboxChargeRequest',
            $charge['requests'][0]['class'],
        );
        $this->assertArrayHasKey('amount_minor', $charge['requests'][0]['fields']);
        $this->assertArrayHasKey('opaque_data.dataValue', $charge['requests'][0]['fields']);
        $this->assertContains('required', $charge['requests'][0]['fields']['amount_minor']);
        $this->assertSame(
            ['App\\Domains\\Payments\\Resources\\PaymentAttemptResource'],
            $charge['response']['serializers'],
        );
        $this->assertSame(0, $contract['scope']['inspection_error_count']);
    }

    public function test_command_can_print_a_domain_filtered_json_contract(): void
    {
        $this->artisan('api:contract', [
            '--domain' => ['Billing'],
            '--json' => true,
        ])->assertSuccessful();
    }

    public function test_the_complete_live_api_contract_has_no_uninspectable_form_requests(): void
    {
        $contract = app(ApiContractInventory::class)->build();
        $expectedOperations = collect(app('router')->getRoutes()->getRoutes())
            ->filter(static fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
            ->sum(static fn (Route $route): int => count(array_diff(
                $route->methods(),
                ['HEAD', 'OPTIONS'],
            )));

        $this->assertSame($expectedOperations, $contract['scope']['operation_count']);
        $this->assertSame(0, $contract['scope']['inspection_error_count']);
    }
}
