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
            'switch_json' => [
                'id' => '+14155550100',
                'state' => 'in_service',
                'features' => ['local', 'inbound_cnam'],
                '_read_only' => ['features' => ['available' => ['cnam', 'e911', 'port']]],
                'cnam' => [
                    'display_name' => 'GridPBX',
                    'inbound_lookup' => true,
                    'provider_status' => 'private-provider-state',
                ],
                'e911' => [
                    'status' => 'PROVISIONED',
                    'caller_name' => 'GridPBX Reception',
                    'street_address' => '100 Main Street',
                    'extended_address' => 'Suite 200',
                    'locality' => 'San Francisco',
                    'region' => 'CA',
                    'postal_code' => '94105',
                    'notification_contact_emails' => ['ops@example.test', 'invalid'],
                    'activated_time' => 'private-provider-time',
                    'location_id' => 'private-provider-id',
                    'latitude' => '37.789',
                    'longitude' => '-122.394',
                    'plus_four' => '1234',
                    'legacy_data' => ['suite' => 'private-legacy-suite'],
                    'provider_status' => 'private-provider-state',
                    'future_provider_field' => ['private' => true],
                ],
                'porting' => [
                    'requested_port_date' => '2026-09-15',
                    'service_provider' => 'Example Carrier',
                    'billing_account_id' => 'private-billing-id',
                    'comments' => ['private note'],
                ],
            ],
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
            ->assertJsonPath('data.cnam.display_name', 'GridPBX')
            ->assertJsonPath('data.e911.street_address', '100 Main Street')
            ->assertJsonPath('data.e911.notification_contact_emails.0', 'ops@example.test')
            ->assertJsonCount(1, 'data.e911.notification_contact_emails')
            ->assertJsonPath('data.porting.active', true)
            ->assertJsonPath('data.porting.service_provider', 'Example Carrier')
            ->assertJsonPath('data.capabilities.available_features.0', 'cnam')
            ->assertJsonPath('data.capabilities.cnam.available', true)
            ->assertJsonPath('data.capabilities.cnam.writable', false)
            ->assertJsonPath(
                'data.capabilities.cnam.reason',
                'Switch reports CNAM as selectable, but the installed notifier workflow does not confirm carrier completion. Mutation remains disabled pending approved quote, charge-confirmation, audit, and reconciliation policy.',
            )
            ->assertJsonMissingPath('data.cnam.provider_status')
            ->assertJsonMissingPath('data.e911.activated_time')
            ->assertJsonMissingPath('data.e911.location_id')
            ->assertJsonMissingPath('data.e911.latitude')
            ->assertJsonMissingPath('data.e911.longitude')
            ->assertJsonMissingPath('data.e911.plus_four')
            ->assertJsonMissingPath('data.e911.legacy_data')
            ->assertJsonMissingPath('data.e911.provider_status')
            ->assertJsonMissingPath('data.e911.future_provider_field')
            ->assertJsonPath('data.capabilities.e911.available', true)
            ->assertJsonPath('data.capabilities.e911.writable', false)
            ->assertJsonPath(
                'data.capabilities.e911.reason',
                'Switch reports E911 as selectable, but selectability does not establish provider readiness or safe emergency caller-ID routing. Mutation remains disabled pending approved emergency-service, verified transport, billing, confirmation, audit, and reconciliation policy.',
            )
            ->assertJsonMissingPath('data.porting.billing_account_id')
            ->assertJsonMissingPath('data.porting.comments')
            ->assertJsonMissingPath('data.switch_json');
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
