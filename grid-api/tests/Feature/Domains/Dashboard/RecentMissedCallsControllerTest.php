<?php

namespace Tests\Feature\Domains\Dashboard;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RecentMissedCallsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_only_the_latest_bounded_inbound_missed_calls_in_the_account_period(): void
    {
        $this->travelTo('2026-08-31 12:00:00');
        [$user, $account] = $this->accessibleAccount(['timezone' => 'America/Los_Angeles']);
        $otherAccount = SwitchAccount::factory()->create();

        foreach (range(1, 6) as $index) {
            SwitchCallDetailRecord::factory()->for($account)->create([
                'direction' => 'inbound',
                'caller_id_name' => "Missed caller {$index}",
                'caller_id_number' => "+1415555010{$index}",
                'callee_id_name' => 'Support',
                'callee_id_number' => '1001',
                'started_at' => "2026-08-31 0{$index}:00:00",
                'billing_seconds' => 0,
                'last_synced_at' => '2026-08-31 11:00:00',
            ]);
        }
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => 'inbound',
            'started_at' => '2026-08-31 10:00:00',
            'billing_seconds' => 30,
        ]);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => 'outbound',
            'started_at' => '2026-08-31 11:00:00',
            'billing_seconds' => 0,
        ]);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => 'inbound',
            'started_at' => '2026-08-20 08:00:00',
            'billing_seconds' => 0,
        ]);
        SwitchCallDetailRecord::factory()->for($otherAccount)->create([
            'direction' => 'inbound',
            'started_at' => '2026-08-31 09:00:00',
            'billing_seconds' => 0,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/recent-missed-calls?range=7d")
            ->assertOk()
            ->assertJsonPath('data.range', '7d')
            ->assertJsonPath('data.timezone', 'America/Los_Angeles')
            ->assertJsonPath('data.total', 6)
            ->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.items.0.caller.name', 'Missed caller 6')
            ->assertJsonPath('data.items.0.destination.number', '1001')
            ->assertJsonPath('data.items.4.caller.name', 'Missed caller 2')
            ->assertJsonMissingPath('data.items.0.call_detail_record_id')
            ->assertJsonMissingPath('data.items.0.switch_json')
            ->assertJsonMissing(['Missed caller 1']);
    }

    public function test_rejects_invalid_ranges_and_other_organization_access(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/recent-missed-calls?range=year")
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('range');

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/recent-missed-calls")
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{User, SwitchAccount}
     */
    private function accessibleAccount(array $attributes = []): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, [
            'role' => OrganizationRole::ReadOnlyUser->value,
        ]);

        return [$user, SwitchAccount::factory()->for($organization)->create($attributes)];
    }
}
