<?php

namespace Tests\Feature\Domains\Dashboard;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CallQualityControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_reliable_quality_metrics_and_disclosed_abandonment_heuristic(): void
    {
        $this->travelTo('2026-08-31 12:00:00');
        [$user, $account] = $this->accessibleAccount(['timezone' => 'UTC']);
        $otherAccount = SwitchAccount::factory()->create();

        foreach ([[50, 40], [70, 50], [30, 20]] as [$duration, $billing]) {
            $this->createCall($account, 'inbound', $duration, $billing);
        }
        foreach ([5, 12, 25] as $duration) {
            $this->createCall($account, 'inbound', $duration, 0);
        }
        $this->createCall($account, 'outbound', 350, 300);
        $this->createCall($otherAccount, 'inbound', 3, 0);
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => 'inbound',
            'duration_seconds' => 1_000,
            'billing_seconds' => 900,
            'started_at' => '2026-07-01 08:00:00',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-quality?range=7d")
            ->assertOk()
            ->assertJsonPath('data.range', '7d')
            ->assertJsonPath('data.answer_time.answered_inbound_calls', 3)
            ->assertJsonPath('data.answer_time.average_pre_answer_seconds', 13)
            ->assertJsonPath('data.potential_abandonment.inbound_calls', 6)
            ->assertJsonPath('data.potential_abandonment.unanswered_inbound_calls', 3)
            ->assertJsonPath('data.potential_abandonment.potential_calls', 2)
            ->assertJsonPath('data.potential_abandonment.rate', 33.3)
            ->assertJsonPath('data.potential_abandonment.threshold_seconds', 15)
            ->assertJsonPath('data.duration_distribution.total_calls', 7)
            ->assertJsonPath('data.duration_distribution.bands.0.count', 3)
            ->assertJsonPath('data.duration_distribution.bands.1.count', 2)
            ->assertJsonPath('data.duration_distribution.bands.2.count', 1)
            ->assertJsonPath('data.duration_distribution.bands.3.count', 1)
            ->assertJsonPath('data.duration_distribution.bands.4.count', 0)
            ->assertJsonMissingPath('data.switch_json');
    }

    public function test_rejects_invalid_ranges_and_other_organization_access(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-quality?range=year")
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('range');

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-quality")
            ->assertNotFound();
    }

    private function createCall(
        SwitchAccount $account,
        string $direction,
        int $duration,
        int $billing,
    ): void {
        SwitchCallDetailRecord::factory()->for($account)->create([
            'direction' => $direction,
            'duration_seconds' => $duration,
            'billing_seconds' => $billing,
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
