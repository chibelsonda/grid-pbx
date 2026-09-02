<?php

namespace Tests\Feature\Support;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiSecurityHardeningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_returns_429_with_retry_headers_after_configured_failed_attempts(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        config([
            'security.rate_limits.login_credentials_per_minute' => 2,
            'security.rate_limits.login_account_per_minute' => 100,
            'security.rate_limits.login_ip_per_minute' => 100,
        ]);
        $user = User::factory()->create(['password' => 'correct-password']);
        $payload = ['email' => $user->email, 'password' => 'incorrect-password'];

        $this->postJson('/login', $payload)->assertUnprocessable();
        $this->postJson('/login', $payload)->assertUnprocessable();
        $response = $this->postJson('/login', $payload);

        $response->assertTooManyRequests()
            ->assertHeader('X-RateLimit-Limit', '2')
            ->assertHeader('X-RateLimit-Remaining', '0');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
    }

    public function test_authenticated_api_limit_isolated_by_user_on_the_same_ip(): void
    {
        config([
            'security.rate_limits.api_user_per_minute' => 1,
            'security.rate_limits.api_ip_per_minute' => 100,
        ]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)->getJson('/api/v1/session')->assertOk();
        $this->actingAs($firstUser)->getJson('/api/v1/session')->assertTooManyRequests();

        $this->actingAs($secondUser)->getJson('/api/v1/session')->assertOk();
    }

    public function test_unauthenticated_api_requests_are_limited_by_ip_before_authentication(): void
    {
        config(['security.rate_limits.api_ip_per_minute' => 1]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->getJson('/api/v1/session')
            ->assertUnauthorized();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->getJson('/api/v1/session')
            ->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.21'])
            ->getJson('/api/v1/session')
            ->assertUnauthorized();
    }

    public function test_sync_mutation_uses_the_stricter_configured_policy(): void
    {
        config([
            'security.rate_limits.api_user_per_minute' => 100,
            'security.rate_limits.api_ip_per_minute' => 100,
            'security.rate_limits.mutation_user_per_minute' => 100,
            'security.rate_limits.sync_user_per_minute' => 1,
        ]);
        Route::middleware(['auth:sanctum', 'throttle:authenticated-api'])
            ->post('/api/_test/sync', static fn () => response()->noContent());
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/_test/sync')->assertNoContent();
        $response = $this->actingAs($user)->postJson('/api/_test/sync');

        $response->assertTooManyRequests()
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_webhook_limit_isolated_by_client_ip(): void
    {
        config(['security.rate_limits.webhook_ip_per_minute' => 1]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/v1/webhooks/authorize-net', [])
            ->assertServiceUnavailable();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/v1/webhooks/authorize-net', [])
            ->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->postJson('/api/v1/webhooks/authorize-net', [])
            ->assertServiceUnavailable();
    }

    public function test_health_check_is_not_rate_limited(): void
    {
        config([
            'security.rate_limits.api_ip_per_minute' => 1,
            'security.rate_limits.webhook_ip_per_minute' => 1,
        ]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->getJson('/api/v1/health')
                ->assertOk()
                ->assertHeaderMissing('X-RateLimit-Limit');
        }
    }

    public function test_oversized_webhook_body_returns_413_before_processing(): void
    {
        config(['security.request_size.webhook_bytes' => 5]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/authorize-net',
            server: [
                'CONTENT_LENGTH' => 6,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '123456',
        );

        $response->assertStatus(413)
            ->assertExactJson(['message' => 'Request payload is too large.']);
    }

    public function test_api_responses_include_defense_in_depth_security_headers(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');

        $this->getJson('/api/v1/session')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_projection_failure_hides_internal_exception_details(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user, ['role' => 'read_only_user']);
        $account = SwitchAccount::factory()->for($organization)->create();
        SyncCheckpoint::query()->create([
            'switch_account_id' => $account->getKey(),
            'resource_type' => 'extensions',
            'status' => ProjectionStatus::Error,
            'error_message' => 'SQLSTATE private-db.internal password=super-secret',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/accounts/{$account->id}/devices");

        $response->assertOk()
            ->assertJsonPath(
                'meta.sync.error_message',
                SyncCheckpoint::PUBLIC_FAILURE_MESSAGE,
            )
            ->assertDontSee('private-db.internal')
            ->assertDontSee('super-secret');
    }
}
