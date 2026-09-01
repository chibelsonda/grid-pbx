<?php

namespace Tests\Feature\Domains\IdentityAccess;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\IdentityAccess\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_returns_401_when_the_user_is_not_authenticated(): void
    {
        $this->patchJson('/api/v1/profile', ['name' => 'Grid Admin'])
            ->assertUnauthorized();
    }

    public function test_returns_422_and_preserves_the_profile_when_the_name_is_empty(): void
    {
        $user = User::factory()->create(['name' => 'Grid Admin']);

        $this->actingAs($user)
            ->patchJson('/api/v1/profile', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name'])
            ->assertJsonPath('errors.name.0', 'Enter your display name.');

        $this->assertSame('Grid Admin', $user->refresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_updates_only_the_authenticated_users_display_name_and_returns_its_public_uuid(): void
    {
        $user = User::factory()->create([
            'name' => 'Grid Admin',
            'email' => 'admin@example.test',
        ]);

        $response = $this->actingAs($user)->patchJson('/api/v1/profile', [
            'name' => 'Operations Admin',
            'email' => 'attacker@example.test',
            'user_id' => 999,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Operations Admin')
            ->assertJsonPath('data.user.email', 'admin@example.test')
            ->assertJsonMissingPath('data.user.user_id');

        $this->assertDatabaseHas('users', [
            'user_id' => $user->getKey(),
            'name' => 'Operations Admin',
            'email' => 'admin@example.test',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->getKey(),
            'organization_id' => null,
            'switch_account_id' => null,
            'action' => 'profile.name_updated',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'outcome' => 'succeeded',
        ]);

        $audit = AuditLog::query()->where('action', 'profile.name_updated')->firstOrFail();
        $this->assertSame([], $audit->metadata);
    }

    public function test_returns_429_after_six_profile_updates_in_one_minute(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 6) as $attempt) {
            $this->actingAs($user)
                ->patchJson('/api/v1/profile', ['name' => "Grid Admin {$attempt}"])
                ->assertOk();
        }

        $this->actingAs($user)
            ->patchJson('/api/v1/profile', ['name' => 'Blocked update'])
            ->assertTooManyRequests();

        $this->assertSame('Grid Admin 6', $user->refresh()->name);
        $this->assertDatabaseCount('audit_logs', 6);
    }
}
