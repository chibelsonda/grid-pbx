<?php

namespace Tests\Feature\Domains\IdentityAccess;

use App\Domains\IdentityAccess\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_a_user_can_log_in_and_read_the_session(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);

        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/v1/session')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

    }

    public function test_an_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/logout')->assertNoContent();

        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_an_unauthenticated_api_request_returns_json(): void
    {
        $this->getJson('/api/v1/session')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
