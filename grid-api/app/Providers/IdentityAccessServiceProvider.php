<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(
            (int) config('identity_access.password.minimum_length'),
        )->mixedCase()->letters()->numbers()->symbols());

        ResetPassword::createUrlUsing(function (
            CanResetPasswordContract $user,
            string $token,
        ): string {
            $query = http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], '', '&', PHP_QUERY_RFC3986);

            return rtrim((string) config('identity_access.frontend_url'), '/')
                .'/reset-password?'.$query;
        });

        RateLimiter::for('forgot-password', fn (Request $request): Limit => Limit::perMinute(
            (int) config('identity_access.rate_limits.forgot_password'),
        )->by($this->passwordResetRateLimitKey($request)));

        RateLimiter::for('reset-password', fn (Request $request): Limit => Limit::perMinute(
            (int) config('identity_access.rate_limits.reset_password'),
        )->by($this->passwordResetRateLimitKey($request)));
    }

    private function passwordResetRateLimitKey(Request $request): string
    {
        $email = Str::lower($request->string('email')->trim()->toString());

        return hash('sha256', $email.'|'.(string) $request->ip());
    }
}
