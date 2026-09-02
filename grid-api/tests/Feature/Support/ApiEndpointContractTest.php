<?php

namespace Tests\Feature\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;
use Tests\TestCase;

class ApiEndpointContractTest extends TestCase
{
    /** @var list<string> */
    private const PUBLIC_API_BOUNDARIES = [
        'GET api/v1/health',
        'POST api/v1/webhooks/authorize-net',
    ];

    public function test_api_routes_require_sanctum_authentication_except_explicit_public_boundaries(): void
    {
        $violations = [];

        foreach ($this->allApiRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            foreach ($this->documentedMethods($route) as $httpMethod) {
                $boundary = "{$httpMethod} {$route->uri()}";

                if (in_array($boundary, self::PUBLIC_API_BOUNDARIES, true)
                    || in_array('auth:sanctum', $middleware, true)) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s (%s) is missing auth:sanctum middleware.',
                    $boundary,
                    $route->getActionName(),
                );
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_api_routes_use_the_expected_rate_limit_boundary(): void
    {
        $violations = [];

        foreach ($this->allApiRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            foreach ($this->documentedMethods($route) as $httpMethod) {
                $boundary = "{$httpMethod} {$route->uri()}";
                $expectedLimiter = match ($boundary) {
                    'GET api/v1/health' => null,
                    'POST api/v1/webhooks/authorize-net' => 'throttle:authorize-net-webhook',
                    default => 'throttle:authenticated-api',
                };

                if ($expectedLimiter === null || in_array($expectedLimiter, $middleware, true)) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s (%s) is missing %s middleware.',
                    $boundary,
                    $route->getActionName(),
                    $expectedLimiter,
                );
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_authenticated_api_routes_have_an_ip_ingress_limit_before_authentication(): void
    {
        $violations = [];

        foreach ($this->apiRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            if (! in_array('auth:sanctum', $middleware, true)
                || in_array('api-ingress', $middleware, true)) {
                continue;
            }

            $violations[] = sprintf(
                '%s (%s) is missing api-ingress middleware.',
                $route->uri(),
                $route->getActionName(),
            );
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_api_mutations_use_form_requests_or_an_explicit_bodyless_boundary(): void
    {
        $violations = [];

        foreach ($this->apiRoutes() as $route) {
            foreach ($this->writeMethods($route) as $httpMethod) {
                $reflection = $this->actionReflection($route);
                $requestClasses = collect($reflection->getParameters())
                    ->map(static function ($parameter): ?string {
                        $type = $parameter->getType();

                        return $type instanceof ReflectionNamedType && ! $type->isBuiltin()
                            ? $type->getName()
                            : null;
                    })
                    ->filter(static fn (?string $class): bool => $class !== null
                        && is_a($class, Request::class, true))
                    ->values();

                if ($requestClasses->contains(
                    static fn (string $class): bool => is_a($class, FormRequest::class, true),
                )) {
                    continue;
                }

                if ($this->isExplicitBodylessOrSignedBoundary($httpMethod, $route->uri())) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s %s (%s) does not use a FormRequest.',
                    $httpMethod,
                    $route->uri(),
                    $route->getActionName(),
                );
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_every_api_form_request_exposes_a_rules_contract(): void
    {
        $missingRules = [];

        foreach ($this->apiRoutes() as $route) {
            $reflection = $this->actionReflection($route);

            foreach ($reflection->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $class = $type->getName();

                if (! is_a($class, FormRequest::class, true) || method_exists($class, 'rules')) {
                    continue;
                }

                $missingRules[] = sprintf(
                    '%s on %s has no rules() contract.',
                    $class,
                    $route->getActionName(),
                );
            }
        }

        $this->assertSame([], array_values(array_unique($missingRules)), implode(PHP_EOL, $missingRules));
    }

    public function test_domain_controllers_do_not_read_unvalidated_request_payloads(): void
    {
        $violations = [];
        $controllers = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Domains')),
        );

        /** @var SplFileInfo $controller */
        foreach ($controllers as $controller) {
            if (! $controller->isFile()
                || ! str_contains($controller->getPathname(), DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR)
                || ! str_ends_with($controller->getFilename(), '.php')) {
                continue;
            }

            $contents = file_get_contents($controller->getPathname());
            if ($contents === false) {
                continue;
            }

            if (preg_match('/\$request->(?:all|input|get|only|except)\s*\(/', $contents) === 1) {
                $violations[] = str_replace(base_path().'/', '', $controller->getPathname());
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Controllers must pass FormRequest validated()/safe() data rather than raw input: '.implode(', ', $violations),
        );
    }

    /** @return list<Route> */
    private function apiRoutes(): array
    {
        return collect($this->allApiRoutes())
            ->filter(static fn (Route $route): bool => $route->getActionName() !== 'Closure')
            ->values()
            ->all();
    }

    /** @return list<Route> */
    private function allApiRoutes(): array
    {
        return collect($this->app['router']->getRoutes()->getRoutes())
            ->filter(static fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function writeMethods(Route $route): array
    {
        return array_values(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']));
    }

    /** @return list<string> */
    private function documentedMethods(Route $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
    }

    private function actionReflection(Route $route): ReflectionMethod
    {
        $action = $route->getActionName();

        if (str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);

            return new ReflectionMethod($controller, $method);
        }

        return new ReflectionMethod($action, '__invoke');
    }

    private function isExplicitBodylessOrSignedBoundary(string $httpMethod, string $uri): bool
    {
        if ($httpMethod === 'DELETE') {
            return true;
        }

        if ($httpMethod === 'POST' && preg_match('#^api/v1/accounts/\{account\}/sync(?:/[^/]+)?$#', $uri) === 1) {
            return true;
        }

        return in_array("{$httpMethod} {$uri}", [
            'PUT api/v1/accounts/{account}/devices/{device}/hotdesk-users/{extension}',
            'POST api/v1/accounts/{account}/payments/webhook-deliveries/{paymentWebhookDelivery}/retry',
            'POST api/v1/webhooks/authorize-net',
        ], true);
    }
}
