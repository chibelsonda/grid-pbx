<?php

namespace Tests\Feature\Domains\Dashboard;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TopCallDestinationsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_bounded_ranked_destinations_for_the_account_period(): void
    {
        $this->travelTo('2026-08-31 12:00:00');
        [$user, $account] = $this->accessibleAccount(['timezone' => 'UTC']);
        $otherAccount = SwitchAccount::factory()->create();

        $this->calls($account, 3, 'Support', '1001', 'inbound', 45);
        $this->calls($account, 2, 'Support', '1001', 'outbound', 0);
        $this->calls($account, 4, 'Sales', '1002', 'inbound', 30);
        foreach (range(3, 7) as $index) {
            $this->calls($account, 1, "Destination {$index}", "10{$index}", 'inbound', 10);
        }
        $this->calls($account, 10, 'Internal bridge', '1000', 'internal', 10);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'callee_id_name' => 'Outside period',
            'callee_id_number' => '1099',
            'started_at' => '2026-07-01 08:00:00',
        ]);
        SwitchCallDetailRecord::factory()->for($otherAccount)->create([
            'callee_id_name' => 'Other tenant',
            'callee_id_number' => '1999',
            'started_at' => '2026-08-31 08:00:00',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/top-destinations?range=7d")
            ->assertOk()
            ->assertJsonPath('data.range', '7d')
            ->assertJsonCount(5, 'data.destinations')
            ->assertJsonPath('data.destinations.0.name', 'Support')
            ->assertJsonPath('data.destinations.0.number', '1001')
            ->assertJsonPath('data.destinations.0.total', 5)
            ->assertJsonPath('data.destinations.0.inbound', 3)
            ->assertJsonPath('data.destinations.0.outbound', 2)
            ->assertJsonPath('data.destinations.0.answered', 3)
            ->assertJsonPath('data.destinations.0.unanswered', 2)
            ->assertJsonPath('data.destinations.1.name', 'Sales')
            ->assertJsonMissing(['Internal bridge'])
            ->assertJsonMissing(['Outside period'])
            ->assertJsonMissing(['Other tenant'])
            ->assertJsonMissingPath('data.destinations.0.switch_account_id');
    }

    public function test_rejects_invalid_ranges_and_other_organization_access(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/top-destinations?range=year")
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('range');

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/top-destinations")
            ->assertNotFound();
    }

    private function calls(
        SwitchAccount $account,
        int $count,
        string $name,
        string $number,
        string $direction,
        int $billingSeconds,
    ): void {
        SwitchCallDetailRecord::factory()->for($account)->count($count)->create([
            'callee_id_name' => $name,
            'callee_id_number' => $number,
            'direction' => $direction,
            'billing_seconds' => $billingSeconds,
            'started_at' => '2026-08-31 08:00:00',
            'last_synced_at' => '2026-08-31 11:00:00',
        ]);
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
