<?php

namespace Tests\Feature\Domains\Blacklists;

use App\Domains\Blacklists\Contracts\SwitchBlacklistGateway;
use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BlacklistControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_creates_and_activates_a_blacklist_with_public_safe_entries(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $gateway = $this->mock(SwitchBlacklistGateway::class);
        $gateway->shouldReceive('activeIds')->once()->withArgs(fn (SwitchAccount $received) => $received->is($account))->andReturn(['existing-list']);
        $gateway->shouldReceive('create')->once()->withArgs(fn (SwitchAccount $received, array $data) => $received->is($account) && $data['numbers'] === ['+15550001000'] && $data['is_active'] === true)->andReturn($this->snapshot());
        $gateway->shouldReceive('setActiveIds')->once()->withArgs(fn (SwitchAccount $received, array $ids) => $received->is($account) && $ids === ['existing-list', 'switch-blacklist-1']);

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/blacklists", $this->payload());

        $response->assertCreated()->assertJsonPath('data.name', 'Known spam')->assertJsonPath('data.is_active', true)->assertJsonPath('data.numbers.0.number', '+15550001000')->assertJsonMissingPath('data.blacklist_id')->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_json');
        $this->assertDatabaseHas('switch_blacklists', ['id' => $response->json('data.id'), 'switch_resource_id' => 'switch-blacklist-1', 'is_active' => true]);
        $this->assertDatabaseHas('switch_blacklist_entries', ['number' => '+15550001000']);
    }

    public function test_active_blacklist_must_be_deactivated_before_delete(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $blacklist = SwitchBlacklist::factory()->for($account)->create(['is_active' => true]);
        $this->mock(SwitchBlacklistGateway::class)->shouldIgnoreMissing();

        $this->actingAs($user)->deleteJson("/api/v1/accounts/{$account->id}/blacklists/{$blacklist->id}")->assertUnprocessable()->assertJsonValidationErrors('blacklist');
    }

    public function test_read_only_user_cannot_create_a_blacklist(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->mock(SwitchBlacklistGateway::class)->shouldIgnoreMissing();
        $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/blacklists", $this->payload())->assertForbidden();
    }

    private function payload(): array { return ['name' => 'Known spam', 'numbers' => ['+15550001000'], 'should_block_anonymous' => true, 'is_active' => true]; }
    private function snapshot(): array { return ['id' => 'switch-blacklist-1', 'name' => 'Known spam', 'numbers' => ['+15550001000' => ['source' => 'manual']], 'should_block_anonymous' => true]; }
    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(OrganizationRole $role = OrganizationRole::AccountOperator): array
    {
        $user = User::factory()->create(); $organization = Organization::factory()->create(); $organization->users()->attach($user, ['role' => $role->value]);
        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
