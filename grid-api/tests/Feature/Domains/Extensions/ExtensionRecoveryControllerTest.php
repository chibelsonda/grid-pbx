<?php

namespace Tests\Feature\Domains\Extensions;

use App\Domains\Extensions\Contracts\SwitchExtensionProvisioningGateway;
use App\Domains\Extensions\Models\ExtensionLifecycleOperation;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\SyncRunStatus;
use App\Domains\SwitchSynchronization\Services\ExtensionSynchronizationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExtensionRecoveryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lists_failed_operations_without_exposing_upstream_resource_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $operation = ExtensionLifecycleOperation::query()->create([
            'switch_account_id' => $account->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'operation' => 'provision',
            'status' => 'failed',
            'completed_steps' => ['user'],
            'failed_step' => 'device',
            'context' => [
                'display_name' => 'Alice Operator',
                'extension' => '1001',
                'resource_ids' => ['user' => 'private-switch-user-id'],
                'compensation_failures' => ['user'],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/extension-recovery")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $operation->id)
            ->assertJsonPath('data.0.recovery_action', 'cleanup')
            ->assertJsonPath('data.0.display_name', 'Alice Operator');

        $this->assertStringNotContainsString('private-switch-user-id', $response->getContent());
        $this->assertStringNotContainsString('resource_ids', $response->getContent());
    }

    public function test_it_retries_failed_provisioning_cleanup_and_marks_the_operation_recovered(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $operation = ExtensionLifecycleOperation::query()->create([
            'switch_account_id' => $account->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'operation' => 'provision',
            'status' => 'failed',
            'completed_steps' => ['user'],
            'failed_step' => 'device',
            'context' => [
                'display_name' => 'Alice Operator',
                'extension' => '1001',
                'resource_ids' => ['user' => 'switch-user-leak'],
                'compensation_failures' => ['user'],
            ],
        ]);
        $gateway = $this->mock(SwitchExtensionProvisioningGateway::class);
        $gateway->shouldReceive('deleteUser')->once()->withArgs(
            fn ($providedAccount, string $resourceId): bool => $providedAccount->is($account)
                && $resourceId === 'switch-user-leak',
        );

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/extension-recovery/{$operation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'recovered')
            ->assertJsonPath('data.repair_required', false)
            ->assertJsonPath('data.recovery_action', 'cleanup');

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/extension-recovery/{$operation->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operation');

        $operation->refresh();
        $this->assertSame([], $operation->context['compensation_failures']);
        $this->assertSame('recovered', $operation->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'extension.recovered',
            'outcome' => 'succeeded',
        ]);
    }

    public function test_it_reconciles_a_partial_update_before_marking_it_recovered(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'is_managed' => true,
            'managed_by_workflow' => 'extension_provisioning',
        ]);
        $operation = ExtensionLifecycleOperation::query()->create([
            'switch_account_id' => $account->getKey(),
            'switch_extension_id' => $extension->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'operation' => 'update',
            'status' => 'failed',
            'completed_steps' => ['user'],
            'failed_step' => 'callflow',
            'context' => [
                'extension_id' => $extension->id,
                'requested_extension' => '1010',
            ],
        ]);
        $this->mock(ExtensionSynchronizationService::class)
            ->shouldReceive('handle')
            ->once()
            ->withArgs(function ($run) use ($account): bool {
                $this->assertTrue($run->switchAccount()->firstOrFail()->is($account));
                $run->forceFill([
                    'status' => SyncRunStatus::Succeeded,
                    'finished_at' => now(),
                ])->save();

                return true;
            });

        $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/extension-recovery/{$operation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'recovered')
            ->assertJsonPath('data.recovery_action', 'reconcile')
            ->assertJsonPath('data.extension_id', $extension->id);

        $this->assertDatabaseHas('switch_sync_runs', [
            'switch_account_id' => $account->getKey(),
            'status' => SyncRunStatus::Succeeded->value,
        ]);
    }

    public function test_read_only_users_cannot_view_the_recovery_queue(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'read_only_user']);
        $account = SwitchAccount::factory()->for($organization)->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/extension-recovery")
            ->assertForbidden();
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'account_operator']);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
