<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class SecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::limit(
                Str::lower(Str::transliterate(trim($request->string('email')->toString()))),
                254,
                '',
            );
            $ipAddress = $request->ip();

            return [
                Limit::perMinute($this->limit('login_credentials_per_minute'))
                    ->by("login-credentials:{$email}|{$ipAddress}"),
                Limit::perMinute($this->limit('login_account_per_minute'))
                    ->by("login-account:{$email}"),
                Limit::perMinute($this->limit('login_ip_per_minute'))
                    ->by("login-ip:{$ipAddress}"),
            ];
        });

        RateLimiter::for('authenticated-api', function (Request $request): array {
            $userKey = (string) $request->user()?->getAuthIdentifier();
            $accountKey = (string) ($request->route('account') ?? 'global');
            $routeKey = (string) ($request->route()?->getActionName() ?? $request->path());
            $limits = [
                Limit::perMinute($this->limit('api_user_per_minute'))->by("api-user:{$userKey}"),
            ];

            if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                $limits[] = Limit::perMinute($this->limit('mutation_user_per_minute'))
                    ->by("mutation:{$userKey}");
            }

            if ($request->isMethod('POST') && str_contains($request->path(), '/sync')) {
                $limits[] = Limit::perMinute($this->limit('sync_user_per_minute'))
                    ->by("sync:{$userKey}|{$accountKey}");
            }

            if ($this->isExpensiveRequest($request)) {
                $limits[] = Limit::perMinute($this->limit('expensive_user_per_minute'))
                    ->by("expensive:{$userKey}|{$routeKey}");
            }

            if ($this->isUploadRequest($request)) {
                $limits[] = Limit::perMinute($this->limit('upload_user_per_minute'))
                    ->by("upload:{$userKey}|{$accountKey}");
            }

            return $limits;
        });

        RateLimiter::for('api-ingress', fn (Request $request): Limit => Limit::perMinute(
            $this->limit('api_ip_per_minute'),
        )->by("api-ingress:{$request->ip()}"));

        RateLimiter::for('sensitive-mutation', function (Request $request): array {
            $routeKey = (string) ($request->route()?->getActionName() ?? $request->path());

            return [
                Limit::perMinute($this->limit('sensitive_mutation_per_minute'))
                    ->by('sensitive-user:'.(string) $request->user()?->getAuthIdentifier()."|{$routeKey}"),
                Limit::perMinute($this->limit('sensitive_mutation_per_minute') * 10)
                    ->by("sensitive-ip:{$request->ip()}|{$routeKey}"),
            ];
        });

        RateLimiter::for('authorize-net-webhook', fn (Request $request): Limit => Limit::perMinute(
            $this->limit('webhook_ip_per_minute'),
        )->by("authorize-net-webhook:{$request->ip()}"));
    }

    private function limit(string $name): int
    {
        return max(1, (int) config("security.rate_limits.{$name}"));
    }

    private function isExpensiveRequest(Request $request): bool
    {
        return $request->is(
            'api/v1/accounts/*/search',
            'api/v1/accounts/*/dashboard*',
            'api/v1/accounts/*/*/statistics',
            'api/v1/accounts/*/recordings/*/audio',
            'api/v1/accounts/*/faxes/*/document',
            'api/v1/accounts/*/billing/*/*/document',
        );
    }

    private function isUploadRequest(Request $request): bool
    {
        return $request->isMethod('POST') && $request->is(
            'api/v1/accounts/*/organization-logo',
            'api/v1/accounts/*/media',
            'api/v1/accounts/*/media/*/audio',
            'api/v1/accounts/*/voicemail-boxes/*/greeting',
        );
    }
}
