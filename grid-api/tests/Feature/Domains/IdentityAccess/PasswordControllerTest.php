<?php

namespace Tests\Feature\Domains\IdentityAccess;

use App\Domains\Auditing\Models\AuditLog;
use App\Domains\IdentityAccess\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_returns_401_when_the_user_is_not_authenticated(): void
    {
        $this->patchJson('/api/v1/password', [
            'current_password' => 'current-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertUnauthorized();
    }

    public function test_returns_422_and_preserves_the_password_when_the_current_password_is_wrong(): void
    {
        $user = User::factory()->create(['password' => 'current-secure-password']);

        $this->actingAs($user)->patchJson('/api/v1/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password'])
            ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');

        $this->assertTrue(Hash::check('current-secure-password', $user->refresh()->password));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_returns_422_when_the_new_password_is_weak_reused_or_unconfirmed(): void
    {
        $user = User::factory()->create(['password' => 'current-secure-password']);

        $this->actingAs($user)->patchJson('/api/v1/password', [
            'current_password' => 'current-secure-password',
            'password' => 'short',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'Use at least 12 characters for your new password.');

        $this->actingAs($user)->patchJson('/api/v1/password', [
            'current_password' => 'current-secure-password',
            'password' => 'current-secure-password',
            'password_confirmation' => 'current-secure-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath(
                'errors.password.0',
                'Choose a new password that differs from your current password.',
            );

        $this->actingAs($user)->patchJson('/api/v1/password', [
            'current_password' => 'current-secure-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password_confirmation'])
            ->assertJsonPath(
                'errors.password_confirmation.0',
                'The new password confirmation does not match.',
            );

        $this->assertTrue(Hash::check('current-secure-password', $user->refresh()->password));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_updates_only_the_authenticated_users_password_and_records_an_audit_event(): void
    {
        $user = User::factory()->create([
            'name' => 'Grid Admin',
            'password' => 'current-secure-password',
            'remember_token' => 'old-remember-token',
        ]);

        $this->actingAs($user)->patchJson('/api/v1/password', [
            'current_password' => 'current-secure-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
            'name' => 'Attacker-controlled name',
        ])->assertNoContent();

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertSame('Grid Admin', $user->name);
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->getKey(),
            'action' => 'profile.password_updated',
            'resource_type' => 'user',
            'resource_id' => $user->id,
            'outcome' => 'succeeded',
        ]);

        $audit = AuditLog::query()->where('action', 'profile.password_updated')->firstOrFail();
        $this->assertSame([], $audit->metadata);
        $auditAttributes = json_encode($audit->getAttributes(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('current-secure-password', $auditAttributes);
        $this->assertStringNotContainsString('new-secure-password', $auditAttributes);
    }
}
