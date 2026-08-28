<?php

namespace Tests\Feature\Domains\Extensions;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchCallflow;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Extensions\Models\SwitchVoicemailBox;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExtensionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lists_and_searches_projected_extensions_for_an_accessible_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        SwitchExtension::factory()->for($account)->create([
            'display_name' => 'Bob Support',
            'extension' => '1002',
        ]);

        $this->actingAs($user)->getJson("/api/v1/accounts/{$account->getKey()}/extensions?search=Alice")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.extension', '1001');
    }

    public function test_it_hides_extensions_from_users_outside_the_account_organization(): void
    {
        $user = User::factory()->create();
        $account = SwitchAccount::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->getKey()}/extensions")
            ->assertNotFound();
    }

    public function test_it_returns_projected_devices_voicemail_and_callflows_for_an_extension(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $extension = SwitchExtension::factory()->for($account)->create([
            'switch_resource_id' => 'switch-user-1',
            'display_name' => 'Alice Operator',
            'extension' => '1001',
        ]);
        SwitchDevice::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'owner_switch_resource_id' => 'switch-user-1',
            'name' => 'Alice Desk Phone',
        ]);
        SwitchVoicemailBox::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'owner_switch_resource_id' => 'switch-user-1',
            'mailbox' => '1001',
        ]);
        SwitchCallflow::factory()->for($account)->create([
            'switch_extension_id' => $extension->getKey(),
            'owner_switch_resource_id' => 'switch-user-1',
            'name' => 'Alice Callflow',
            'numbers' => ['1001'],
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->getKey()}/extensions/{$extension->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Alice Operator')
            ->assertJsonPath('data.devices.0.name', 'Alice Desk Phone')
            ->assertJsonPath('data.voicemail_boxes.0.mailbox', '1001')
            ->assertJsonPath('data.callflows.0.numbers.0', '1001');
    }

    public function test_it_returns_404_when_the_extension_belongs_to_another_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherExtension = SwitchExtension::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->getKey()}/extensions/{$otherExtension->getKey()}")
            ->assertNotFound();
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
