<?php

namespace Tests\Feature\Domains\PhoneNumbers;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\PhoneNumbers\Jobs\SyncSwitchPhoneNumbersJob;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PhoneNumberControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_lists_filters_and_shows_phone_numbers_using_public_ids(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $callflow = SwitchCallflow::factory()->for($account)->create([
            'name' => 'Main Reception',
            'numbers' => ['+14155550100'],
        ]);
        $assigned = SwitchPhoneNumber::factory()->for($account)->create([
            'assigned_callflow_id' => $callflow->getKey(),
            'number' => '+14155550100',
            'state' => 'in_service',
            'used_by' => 'callflow',
            'features' => ['local', 'inbound_cnam'],
            'cnam_display_name' => 'GridPBX',
        ]);
        SwitchPhoneNumber::factory()->for($account)->create([
            'number' => '+14155550101',
            'state' => 'reserved',
            'features' => ['local'],
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/phone-numbers?search=Reception&state=in_service&assignment=assigned&feature=inbound_cnam")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id)
            ->assertJsonPath('data.0.number', '+14155550100')
            ->assertJsonPath('data.0.assigned_callflow.id', $callflow->id)
            ->assertJsonMissingPath('data.0.phone_number_id')
            ->assertJsonMissingPath('data.0.assigned_callflow_id')
            ->assertJsonMissingPath('data.0.switch_json');

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/phone-numbers/{$assigned->id}")
            ->assertOk()
            ->assertJsonPath('data.cnam.display_name', 'GridPBX');
    }

    public function test_it_hides_numbers_outside_the_accessible_account(): void
    {
        [$user, $account] = $this->accessibleAccount();
        $otherNumber = SwitchPhoneNumber::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/phone-numbers/{$otherNumber->id}")
            ->assertNotFound();
    }

    public function test_it_queues_a_phone_number_sync_and_reuses_an_active_run(): void
    {
        Queue::fake();
        [$user, $account] = $this->accessibleAccount();

        $first = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/phone-numbers")
            ->assertAccepted()
            ->json('data.id');
        $second = $this->actingAs($user)
            ->postJson("/api/v1/accounts/{$account->id}/sync/phone-numbers")
            ->assertAccepted()
            ->json('data.id');

        $this->assertSame($first, $second);
        Queue::assertPushed(SyncSwitchPhoneNumbersJob::class, 1);
        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/sync/phone-numbers/{$first}")
            ->assertOk()
            ->assertJsonPath('data.resource_type', 'phone_numbers')
            ->assertJsonPath('data.status', 'queued');
    }

    /** @return array{User, SwitchAccount} */
    private function accessibleAccount(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->getKey(), [
            'role' => OrganizationRole::AccountOperator->value,
        ]);
        $account = SwitchAccount::factory()->for($organization)->create();

        return [$user, $account];
    }
}
