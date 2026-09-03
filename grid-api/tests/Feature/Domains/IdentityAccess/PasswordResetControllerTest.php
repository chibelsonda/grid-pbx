<?php

namespace Tests\Feature\Domains\IdentityAccess;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\IdentityAccess\Notifications\PasswordResetNotification;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string GENERIC_LINK_MESSAGE =
        'If an account exists for that email address, a password reset link has been sent.';

    private const string INVALID_TOKEN_MESSAGE =
        'This password reset link is invalid or has expired. Request a new link and try again.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        config([
            'app.name' => 'GridPBX',
            'identity_access.frontend_url' => 'https://console.example.test',
            'identity_access.rate_limits.forgot_password' => 20,
            'identity_access.rate_limits.reset_password' => 20,
        ]);
    }

    public function test_known_email_returns_202_and_sends_branded_reset_instructions(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);
        Notification::fake();

        $response = $this->postJson('/forgot-password', [
            'email' => ' OWNER@EXAMPLE.TEST ',
        ]);

        $response
            ->assertAccepted()
            ->assertExactJson(['data' => ['message' => self::GENERIC_LINK_MESSAGE]])
            ->assertJsonMissing(['email' => $user->email]);
        Notification::assertSentTo(
            $user,
            PasswordResetNotification::class,
            function (PasswordResetNotification $notification) use ($user): bool {
                $mail = $notification->toMail($user);
                $url = (string) $mail->actionUrl;
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

                $this->assertSame('Reset your GridPBX password', $mail->subject);
                $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
                $this->assertSame('console.example.test', parse_url($url, PHP_URL_HOST));
                $this->assertSame('/reset-password', parse_url($url, PHP_URL_PATH));
                $this->assertSame($notification->token, $query['token'] ?? null);
                $this->assertSame($user->email, $query['email'] ?? null);
                $this->assertContains(
                    'This password reset link will expire in 60 minutes.',
                    $mail->outroLines,
                );
                $renderedMail = $mail->render();

                $this->assertStringContainsString('GridPBX', $renderedMail);
                $this->assertStringContainsString('background-color: #3f6ad8', $renderedMail);

                return true;
            },
        );

        $storedToken = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertIsString($storedToken);
        $this->assertNotSame('', $storedToken);
    }

    public function test_unknown_email_returns_the_same_202_response_without_sending_a_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/forgot-password', [
            'email' => 'missing@example.test',
        ]);

        $response->assertAccepted()->assertExactJson([
            'data' => ['message' => self::GENERIC_LINK_MESSAGE],
        ]);
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing@example.test']);
    }

    public function test_reset_notification_is_queued_after_commit_with_an_encrypted_payload(): void
    {
        User::factory()->create(['email' => 'queued@example.test']);
        Queue::fake([SendQueuedNotifications::class]);

        $this->postJson('/forgot-password', [
            'email' => 'queued@example.test',
        ])->assertAccepted();

        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof PasswordResetNotification
                && $job->shouldBeEncrypted
                && $job->afterCommit === true,
        );
    }

    public function test_notification_dispatch_failure_is_reported_but_still_returns_the_generic_202_response(): void
    {
        Exceptions::fake();
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new RuntimeException('Notification dispatch unavailable.'));

        $response = $this->postJson('/forgot-password', [
            'email' => 'owner@example.test',
        ]);

        $response->assertAccepted()->assertExactJson([
            'data' => ['message' => self::GENERIC_LINK_MESSAGE],
        ]);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_forgot_password_returns_422_for_a_malformed_email(): void
    {
        Notification::fake();

        $this->postJson('/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Notification::assertNothingSent();
    }

    public function test_forgot_password_returns_429_after_the_configured_request_limit(): void
    {
        config(['identity_access.rate_limits.forgot_password' => 2]);

        $payload = ['email' => 'limited@example.test'];

        $this->postJson('/forgot-password', $payload)->assertAccepted();
        $this->postJson('/forgot-password', $payload)->assertAccepted();
        $this->postJson('/forgot-password', $payload)->assertTooManyRequests();
    }

    public function test_valid_token_resets_password_rotates_remember_token_and_cannot_be_replayed(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.test',
            'password' => 'Old-password1!',
            'remember_token' => 'old-remember-token',
        ]);
        $token = Password::broker()->createToken($user);
        $storedToken = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        Event::fake([PasswordResetEvent::class]);
        $payload = $this->validResetPayload($user, $token);

        $response = $this->postJson('/reset-password', $payload);

        $response->assertOk()->assertExactJson([
            'data' => [
                'message' => 'Your password has been reset. You can now sign in with your new password.',
            ],
        ]);
        $this->assertIsString($storedToken);
        $this->assertNotSame($token, $storedToken);
        $this->assertTrue(Hash::check($token, $storedToken));
        $this->assertTrue(Hash::check('New-password2!', $user->fresh()->password));
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Event::assertDispatched(
            PasswordResetEvent::class,
            fn (PasswordResetEvent $event): bool => $event->user->is($user),
        );

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'Old-password1!',
        ])->assertUnprocessable();
        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'New-password2!',
        ])->assertOk();

        $this->postJson('/logout')->assertNoContent();
        $this->postJson('/reset-password', $payload)
            ->assertUnprocessable()
            ->assertExactJson(['message' => self::INVALID_TOKEN_MESSAGE]);
    }

    public function test_invalid_token_returns_a_safe_422_without_changing_the_password(): void
    {
        $user = User::factory()->create(['password' => 'Old-password1!']);

        $response = $this->postJson('/reset-password', $this->validResetPayload($user, 'invalid'));

        $response
            ->assertUnprocessable()
            ->assertExactJson(['message' => self::INVALID_TOKEN_MESSAGE]);
        $this->assertTrue(Hash::check('Old-password1!', $user->fresh()->password));
    }

    public function test_expired_token_returns_a_safe_422_without_changing_the_password(): void
    {
        config(['auth.passwords.users.expire' => 1]);
        $user = User::factory()->create(['password' => 'Old-password1!']);
        $token = Password::broker()->createToken($user);
        $this->travel(2)->minutes();

        $response = $this->postJson('/reset-password', $this->validResetPayload($user, $token));

        $response
            ->assertUnprocessable()
            ->assertExactJson(['message' => self::INVALID_TOKEN_MESSAGE]);
        $this->assertTrue(Hash::check('Old-password1!', $user->fresh()->password));
    }

    public function test_reset_password_returns_422_for_confirmation_mismatch(): void
    {
        $user = User::factory()->create();
        $payload = $this->validResetPayload($user, 'unused-token');
        $payload['password_confirmation'] = 'Different-password3!';

        $this->postJson('/reset-password', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_reset_password_returns_422_for_a_weak_password(): void
    {
        $user = User::factory()->create();
        $payload = $this->validResetPayload($user, 'unused-token');
        $payload['password'] = 'weak-password';
        $payload['password_confirmation'] = 'weak-password';

        $this->postJson('/reset-password', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_reset_password_returns_429_after_the_configured_request_limit(): void
    {
        config(['identity_access.rate_limits.reset_password' => 2]);
        $user = User::factory()->create();
        $payload = $this->validResetPayload($user, 'invalid-token');

        $this->postJson('/reset-password', $payload)->assertUnprocessable();
        $this->postJson('/reset-password', $payload)->assertUnprocessable();
        $this->postJson('/reset-password', $payload)->assertTooManyRequests();
    }

    public function test_successful_reset_revokes_database_sessions_for_the_user(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => 'Old-password1!']);
        $otherUser = User::factory()->create();
        DB::table('sessions')->insert([
            $this->sessionRecord('target-session', $user->getKey()),
            $this->sessionRecord('other-session', $otherUser->getKey()),
        ]);
        $token = Password::broker()->createToken($user);

        $this->postJson('/reset-password', $this->validResetPayload($user, $token))->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    }

    /** @return array<string, string> */
    private function validResetPayload(User $user, string $token): array
    {
        return [
            'email' => $user->email,
            'token' => $token,
            'password' => 'New-password2!',
            'password_confirmation' => 'New-password2!',
        ];
    }

    /** @return array<string, int|string|null> */
    private function sessionRecord(string $id, int $userId): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Password reset test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ];
    }
}
