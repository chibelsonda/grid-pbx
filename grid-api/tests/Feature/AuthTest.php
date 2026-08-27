<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_and_receive_their_organization_context(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $organization = Organization::query()->create([
            'name' => 'GridPBX',
            'slug' => 'gridpbx',
        ]);
        $organization->users()->attach($user, ['role' => 'platform_administrator']);

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.organizations.0.slug', 'gridpbx');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_an_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/logout')->assertNoContent();

        $this->assertGuest();
    }

    public function test_an_unauthenticated_api_request_returns_json_without_redirecting(): void
    {
        $this->get('/api/v1/session')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
