<?php

namespace Tests\Feature\Domains\Menus;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Menus\Contracts\SwitchMenuGateway;
use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MenuControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_creates_menu_with_resolved_media_and_public_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $media = SwitchMedia::factory()->for($account)->create(['switch_resource_id' => 'switch-media-1']);
        $gateway = $this->mock(SwitchMenuGateway::class);
        $gateway->shouldReceive('create')->once()->withArgs(function (SwitchAccount $received, array $data) use ($account): bool {
            return $received->is($account)
                && $data['switch_greeting_media_reference'] === 'switch-media-1'
                && $data['switch_invalid_media'] === 'switch-media-1'
                && $data['switch_transfer_media'] === true
                && $data['switch_exit_media'] === false;
        })->andReturn($this->snapshot(['media' => ['greeting' => 'switch-media-1', 'invalid_media' => 'switch-media-1', 'transfer_media' => true, 'exit_media' => false]]));

        $response = $this->actingAs($user)->postJson("/api/v1/accounts/{$account->id}/menus", [
            ...$this->payload(), 'greeting_media_id' => $media->id, 'invalid_media_id' => $media->id,
            'exit_media_enabled' => false,
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Main menu')
            ->assertJsonPath('data.greeting_media.id', $media->id)
            ->assertJsonPath('data.invalid_media.id', $media->id)
            ->assertJsonPath('data.exit_media_enabled', false)
            ->assertJsonPath('data.record_pin_configured', false)
            ->assertJsonMissingPath('data.record_pin')
            ->assertJsonMissingPath('data.menu_id')->assertJsonMissingPath('data.switch_resource_id')->assertJsonMissingPath('data.switch_json');
        $this->assertDatabaseHas('switch_menus', ['id' => $response->json('data.id'), 'switch_resource_id' => 'switch-menu-1']);
    }

    public function test_read_only_user_cannot_mutate_and_cross_tenant_media_is_rejected(): void
    {
        $this->mock(SwitchMenuGateway::class)->shouldNotReceive('create');
        [$readOnly, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $this->actingAs($readOnly)->postJson("/api/v1/accounts/{$account->id}/menus", $this->payload())->assertForbidden();

        [$operator, $managed] = $this->accessibleAccount();
        $foreignMedia = SwitchMedia::factory()->create();
        $this->actingAs($operator)->postJson("/api/v1/accounts/{$managed->id}/menus", [
            ...$this->payload(), 'greeting_media_id' => $foreignMedia->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('greeting_media_id');
    }

    public function test_accessible_user_lists_only_account_menus(): void
    {
        [$user, $account] = $this->accessibleAccount(OrganizationRole::ReadOnlyUser);
        $menu = SwitchMenu::factory()->for($account)->create(['name' => 'Main menu']);
        SwitchMenu::factory()->create();
        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}/menus")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $menu->id)
            ->assertJsonMissingPath('data.0.switch_resource_id');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'name' => 'Main menu', 'timeout' => 10000, 'interdigit_timeout' => 2000,
            'max_extension_length' => 4, 'retries' => 3, 'hunt' => true,
            'allow_record_from_offnet' => false, 'suppress_media' => false,
            'record_pin' => null, 'hunt_allow' => null, 'hunt_deny' => null,
            'greeting_media_id' => null, 'invalid_media_enabled' => true, 'invalid_media_id' => null,
            'transfer_media_enabled' => true, 'transfer_media_id' => null,
            'exit_media_enabled' => true, 'exit_media_id' => null,
        ];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function snapshot(array $overrides = []): array
    {
        return [
            'id' => 'switch-menu-1', 'name' => 'Main menu', 'timeout' => 10000,
            'interdigit_timeout' => 2000, 'max_extension_length' => 4, 'retries' => 3,
            'hunt' => true, 'allow_record_from_offnet' => false, 'suppress_media' => false,
            'media' => [], ...$overrides,
        ];
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(OrganizationRole $role = OrganizationRole::AccountOperator): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => $role->value]);

        return [$user, SwitchAccount::factory()->for($organization)->create()];
    }
}
