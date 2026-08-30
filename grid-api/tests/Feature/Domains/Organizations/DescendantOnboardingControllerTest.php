<?php

namespace Tests\Feature\Domains\Organizations;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Contracts\SwitchAccountGateway;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\Services\Jobs\SyncSwitchServicesJob;
use App\Domains\Services\Services\StartServiceSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class DescendantOnboardingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reseller_administrator_onboards_confirmed_descendant_and_returns_201(): void
    {
        Queue::fake([SyncSwitchServicesJob::class]);
        $actor = User::factory()->create();
        $existingMember = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Grid Reseller']);
        $organization->users()->attach($actor, ['role' => 'reseller_administrator']);
        $organization->users()->attach($existingMember, ['role' => 'account_operator']);
        $scope = SwitchAccount::factory()->for($organization)->create([
            'switch_account_id' => 'switch-reseller',
            'name' => 'Grid Reseller',
            'is_reseller' => true,
            'descendants_count' => 1,
        ]);
        $candidate = [
            'id' => 'switch-child',
            'name' => 'Acme Child',
            'realm' => 'acme.example.test',
            'tree' => ['switch-reseller'],
            'parent_id' => 'switch-reseller',
            'descendants_count' => 0,
        ];
        $gateway = $this->mock(SwitchAccountGateway::class);
        $gateway->shouldReceive('descendants')->twice()->andReturn([$candidate]);
        $gateway->shouldReceive('findBySwitchAccountId')->once()->with('switch-child')->andReturn([
            'id' => 'switch-child',
            'name' => 'Acme Child',
            'realm' => 'acme.example.test',
            'tree' => ['switch-reseller'],
            'parent_id' => 'switch-reseller',
            'enabled' => true,
            'is_reseller' => false,
            'superduper_admin' => false,
        ]);
        $gateway->shouldReceive('find')->once()->andReturn([
            'id' => 'switch-reseller',
            'name' => 'Grid Reseller',
            'tree' => [],
            'is_reseller' => true,
            'descendants_count' => 1,
        ]);

        $discovery = $this->actingAs($actor)
            ->getJson("/api/v1/accounts/{$scope->id}/descendant-onboarding")
            ->assertOk()
            ->assertJsonPath('data.candidates.0.name', 'Acme Child')
            ->assertJsonPath('data.candidates.0.eligible', true)
            ->assertJsonPath('data.target_organization.id', $organization->id)
            ->assertJsonPath('data.access_inheritance.member_count', 2)
            ->assertJsonPath('data.access_inheritance.acknowledgement_required', true)
            ->assertJsonMissingPath('data.candidates.0.switch_account_id')
            ->assertJsonMissingPath('data.candidates.0.account_id');
        $reference = $discovery->json('data.candidates.0.reference');

        $response = $this->actingAs($actor)->postJson(
            "/api/v1/accounts/{$scope->id}/descendant-onboarding",
            [
                'reference' => $reference,
                'confirmation' => 'Acme Child',
                'acknowledge_existing_access' => true,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.onboarded_account.name', 'Acme Child')
            ->assertJsonPath('data.onboarded_account.realm', 'acme.example.test')
            ->assertJsonPath('data.target_organization.id', $organization->id)
            ->assertJsonPath('data.access_inheritance.member_count', 2)
            ->assertJsonPath('data.access_inheritance.acknowledged', true)
            ->assertJsonPath('data.hierarchy.children.0.name', 'Acme Child')
            ->assertJsonPath('data.hierarchy.coverage.unresolved_descendants_count', 0)
            ->assertJsonPath('data.service_projection.status', 'queued')
            ->assertJsonMissingPath('data.onboarded_account.switch_account_id')
            ->assertJsonMissingPath('data.onboarded_account.account_id');

        $onboardedAccountId = $response->json('data.onboarded_account.id');
        $this->assertIsString($onboardedAccountId);
        $this->assertDatabaseHas('switch_accounts', [
            'id' => $onboardedAccountId,
            'organization_id' => $organization->getKey(),
            'switch_account_id' => 'switch-child',
            'parent_account_id' => $scope->getKey(),
            'parent_switch_account_id' => 'switch-reseller',
            'sync_status' => 'synced',
            'descendants_count' => 0,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'switch_account_id' => $scope->getKey(),
            'action' => 'reseller_descendant.onboard',
            'resource_type' => 'switch_account',
            'resource_id' => $onboardedAccountId,
            'outcome' => 'succeeded',
        ]);
        $this->assertDatabaseHas('switch_sync_runs', [
            'id' => $response->json('data.service_projection.sync_run_id'),
            'switch_account_id' => SwitchAccount::query()->where('id', $onboardedAccountId)->value('account_id'),
            'requested_by_user_id' => $actor->getKey(),
            'resource_type' => 'services',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SyncSwitchServicesJob::class, function (SyncSwitchServicesJob $job) use ($onboardedAccountId): bool {
            $accountKey = SwitchAccount::query()->where('id', $onboardedAccountId)->value('account_id');

            return $job->switchAccountId === (string) $accountKey;
        });
    }

    public function test_onboarding_remains_successful_when_service_projection_cannot_start(): void
    {
        Exceptions::fake([RuntimeException::class]);
        [$actor, $organization, $scope] = $this->resellerScope();
        $gateway = $this->mock(SwitchAccountGateway::class);
        $gateway->shouldReceive('descendants')->twice()->andReturn([$this->candidate()]);
        $gateway->shouldReceive('findBySwitchAccountId')->once()->andReturn([
            ...$this->candidate(),
            'enabled' => true,
            'is_reseller' => false,
            'superduper_admin' => false,
        ]);
        $gateway->shouldReceive('find')->once()->andReturn([
            'id' => 'switch-reseller',
            'name' => 'Grid Reseller',
            'tree' => [],
            'is_reseller' => true,
            'descendants_count' => 1,
        ]);
        $this->mock(StartServiceSyncService::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('redis://private-host:6379 secret-token'));
        $reference = $this->actingAs($actor)
            ->getJson("/api/v1/accounts/{$scope->id}/descendant-onboarding")
            ->assertOk()
            ->json('data.candidates.0.reference');

        $response = $this->actingAs($actor)->postJson(
            "/api/v1/accounts/{$scope->id}/descendant-onboarding",
            [
                'reference' => $reference,
                'confirmation' => 'Acme Child',
                'acknowledge_existing_access' => true,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.onboarded_account.name', 'Acme Child')
            ->assertJsonPath('data.service_projection.status', 'not_started')
            ->assertJsonPath('data.service_projection.sync_run_id', null)
            ->assertDontSee('private-host')
            ->assertDontSee('secret-token');
        $this->assertDatabaseHas('switch_accounts', [
            'organization_id' => $organization->getKey(),
            'switch_account_id' => 'switch-child',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'switch_account_id' => $scope->getKey(),
            'action' => 'reseller_descendant.onboard',
            'outcome' => 'succeeded',
        ]);
        Exceptions::assertReported(fn (RuntimeException $exception): bool => str_contains(
            $exception->getMessage(),
            'private-host',
        ));
    }

    public function test_returns_422_without_existing_access_acknowledgement(): void
    {
        [$actor, , $scope] = $this->resellerScope();
        $gateway = $this->mock(SwitchAccountGateway::class);
        $gateway->shouldReceive('descendants')->once()->andReturn([$this->candidate()]);
        $reference = $this->actingAs($actor)
            ->getJson("/api/v1/accounts/{$scope->id}/descendant-onboarding")
            ->assertOk()
            ->json('data.candidates.0.reference');

        $response = $this->actingAs($actor)->postJson(
            "/api/v1/accounts/{$scope->id}/descendant-onboarding",
            [
                'reference' => $reference,
                'confirmation' => 'Acme Child',
                'acknowledge_existing_access' => false,
            ],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('acknowledge_existing_access')
            ->assertJsonPath(
                'errors.acknowledge_existing_access.0',
                'Acknowledge that existing organization members will inherit access to the onboarded account.',
            );
        $this->assertDatabaseCount('switch_accounts', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_returns_422_when_reference_is_used_by_another_actor(): void
    {
        [$actor, $organization, $scope] = $this->resellerScope();
        $otherActor = User::factory()->create();
        $organization->users()->attach($otherActor, ['role' => 'reseller_administrator']);
        $gateway = $this->mock(SwitchAccountGateway::class);
        $gateway->shouldReceive('descendants')->once()->andReturn([$this->candidate()]);
        $reference = $this->actingAs($actor)
            ->getJson("/api/v1/accounts/{$scope->id}/descendant-onboarding")
            ->assertOk()
            ->json('data.candidates.0.reference');

        $response = $this->actingAs($otherActor)->postJson(
            "/api/v1/accounts/{$scope->id}/descendant-onboarding",
            [
                'reference' => $reference,
                'confirmation' => 'Acme Child',
                'acknowledge_existing_access' => true,
            ],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reference')
            ->assertJsonPath(
                'errors.reference.0',
                'The descendant reference is invalid or expired. Refresh the candidate list and try again.',
            );
        $this->assertDatabaseCount('switch_accounts', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_returns_422_when_descendant_reference_has_expired(): void
    {
        $this->travelTo('2026-08-30 10:00:00');
        [$actor, , $scope] = $this->resellerScope();
        $gateway = $this->mock(SwitchAccountGateway::class);
        $gateway->shouldReceive('descendants')->once()->andReturn([$this->candidate()]);
        $reference = $this->actingAs($actor)
            ->getJson("/api/v1/accounts/{$scope->id}/descendant-onboarding")
            ->assertOk()
            ->json('data.candidates.0.reference');
        $this->travelTo('2026-08-30 10:11:00');

        $response = $this->actingAs($actor)->postJson(
            "/api/v1/accounts/{$scope->id}/descendant-onboarding",
            [
                'reference' => $reference,
                'confirmation' => 'Acme Child',
                'acknowledge_existing_access' => true,
            ],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reference')
            ->assertJsonPath(
                'errors.reference.0',
                'The descendant reference is invalid or expired. Refresh the candidate list and try again.',
            );
        $this->assertDatabaseCount('switch_accounts', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_returns_409_when_immediate_parent_is_not_projected(): void
    {
        [$actor, , $scope] = $this->resellerScope();
        $candidate = [
            ...$this->candidate(),
            'tree' => ['switch-reseller', 'switch-unmanaged-parent'],
            'parent_id' => 'switch-unmanaged-parent',
        ];
        $gateway = $this->mock(SwitchAccountGateway::class);
        $gateway->shouldReceive('descendants')->twice()->andReturn([$candidate]);
        $discovery = $this->actingAs($actor)
            ->getJson("/api/v1/accounts/{$scope->id}/descendant-onboarding")
            ->assertOk()
            ->assertJsonPath('data.candidates.0.eligible', false)
            ->assertJsonPath('data.candidates.0.blocked_reason', 'parent_not_projected');

        $response = $this->actingAs($actor)->postJson(
            "/api/v1/accounts/{$scope->id}/descendant-onboarding",
            [
                'reference' => $discovery->json('data.candidates.0.reference'),
                'confirmation' => 'Acme Child',
                'acknowledge_existing_access' => true,
            ],
        );

        $response->assertConflict()
            ->assertJsonPath('message', 'Onboard the selected account parent before onboarding this descendant.');
        $this->assertDatabaseCount('switch_accounts', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_account_administrator_is_forbidden_from_descendant_onboarding(): void
    {
        $actor = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($actor, ['role' => 'account_administrator']);
        $scope = SwitchAccount::factory()->for($organization)->create(['is_reseller' => true]);
        $this->mock(SwitchAccountGateway::class)
            ->shouldNotReceive('descendants');

        $this->actingAs($actor)
            ->getJson("/api/v1/accounts/{$scope->id}/descendant-onboarding")
            ->assertForbidden();
    }

    /** @return array{User, Organization, SwitchAccount} */
    private function resellerScope(): array
    {
        $actor = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($actor, ['role' => 'reseller_administrator']);
        $scope = SwitchAccount::factory()->for($organization)->create([
            'switch_account_id' => 'switch-reseller',
            'name' => 'Grid Reseller',
            'is_reseller' => true,
            'descendants_count' => 1,
        ]);

        return [$actor, $organization, $scope];
    }

    /** @return array<string, mixed> */
    private function candidate(): array
    {
        return [
            'id' => 'switch-child',
            'name' => 'Acme Child',
            'realm' => 'acme.example.test',
            'tree' => ['switch-reseller'],
            'parent_id' => 'switch-reseller',
            'descendants_count' => 0,
        ];
    }
}
