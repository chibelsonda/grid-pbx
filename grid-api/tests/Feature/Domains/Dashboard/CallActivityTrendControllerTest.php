<?php

namespace Tests\Feature\Domains\Dashboard;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Enums\OrganizationRole;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CallActivityTrendControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_a_timezone_aware_seven_day_activity_series_and_summary(): void
    {
        $this->travelTo('2026-08-31 12:00:00');
        [$user, $account] = $this->accessibleAccount(['timezone' => 'America/Los_Angeles']);
        $this->createCall($account, '2026-08-25 06:59:59', 'inbound', 90);
        $this->createCall($account, '2026-08-25 07:00:00', 'inbound', 60);
        $this->createCall($account, '2026-08-31 06:59:59', 'outbound', 0);
        $this->createCall($account, '2026-08-31 07:00:00', 'inbound', 30);
        $this->createCall($account, '2026-09-01 07:00:00', 'outbound', 45);
        $this->createCall($account, '2026-08-31 08:00:00', 'internal', 15);

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/dashboard/call-activity?range=7d",
        );

        $response->assertOk()
            ->assertJsonPath('data.range', '7d')
            ->assertJsonPath('data.granularity', 'day')
            ->assertJsonPath('data.timezone', 'America/Los_Angeles')
            ->assertJsonPath('data.from', '2026-08-25T00:00:00-07:00')
            ->assertJsonPath('data.to', '2026-09-01T00:00:00-07:00')
            ->assertJsonCount(7, 'data.series')
            ->assertJsonPath('data.series.0.total', 1)
            ->assertJsonPath('data.series.5.total', 1)
            ->assertJsonPath('data.series.6.total', 1)
            ->assertJsonPath('data.totals.total', 3)
            ->assertJsonPath('data.totals.inbound', 2)
            ->assertJsonPath('data.totals.outbound', 1)
            ->assertJsonPath('data.totals.answered', 2)
            ->assertJsonPath('data.totals.missed', 1)
            ->assertJsonPath('data.totals.answer_rate', 66.7)
            ->assertJsonPath('data.totals.average_duration_seconds', 45)
            ->assertJsonMissingPath('data.switch_account_id');
    }

    #[DataProvider('supportedRanges')]
    public function test_returns_the_expected_granularity_and_bucket_count(
        string $range,
        string $granularity,
        int $bucketCount,
    ): void {
        $this->travelTo('2026-08-31 12:00:00');
        [$user, $account] = $this->accessibleAccount(['timezone' => 'UTC']);

        $response = $this->actingAs($user)->getJson(
            "/api/v1/accounts/{$account->id}/dashboard/call-activity?range={$range}",
        );

        $response->assertOk()
            ->assertJsonPath('data.range', $range)
            ->assertJsonPath('data.granularity', $granularity)
            ->assertJsonCount($bucketCount, 'data.series');
    }

    public function test_defaults_to_seven_days_when_the_range_is_omitted(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-activity")
            ->assertOk()
            ->assertJsonPath('data.range', '7d')
            ->assertJsonCount(7, 'data.series');
    }

    public function test_rejects_an_unsupported_range(): void
    {
        [$user, $account] = $this->accessibleAccount();

        $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-activity?range=year;drop-table")
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('range')
            ->assertJsonPath('errors.range.0', 'The selected call activity range is invalid.');
    }

    public function test_returns_401_for_an_unauthenticated_request(): void
    {
        $account = SwitchAccount::factory()->create();

        $this->getJson("/api/v1/accounts/{$account->id}/dashboard/call-activity")
            ->assertUnauthorized();
    }

    public function test_returns_404_for_another_organizations_account(): void
    {
        [, $account] = $this->accessibleAccount();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/accounts/{$account->id}/dashboard/call-activity")
            ->assertNotFound();
    }

    /** @return array<string, array{string, string, int}> */
    public static function supportedRanges(): array
    {
        return [
            'today by hour' => ['today', 'hour', 24],
            'thirty days by day' => ['30d', 'day', 30],
        ];
    }

    private function createCall(
        SwitchAccount $account,
        string $startedAt,
        string $direction,
        int $billingSeconds,
    ): SwitchCallDetailRecord {
        return SwitchCallDetailRecord::factory()->for($account)->create([
            'started_at' => $startedAt,
            'direction' => $direction,
            'billing_seconds' => $billingSeconds,
        ]);
    }

    /**
     * @param  array<string, mixed>  $accountAttributes
     * @return array{User, SwitchAccount}
     */
    private function accessibleAccount(array $accountAttributes = []): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, [
            'role' => OrganizationRole::ReadOnlyUser->value,
        ]);

        return [
            $user,
            SwitchAccount::factory()->for($organization)->create($accountAttributes),
        ];
    }
}
