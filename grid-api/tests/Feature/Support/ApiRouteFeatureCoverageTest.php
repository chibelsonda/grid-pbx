<?php

namespace Tests\Feature\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class ApiRouteFeatureCoverageTest extends TestCase
{
    /**
     * Routes exercised through a local endpoint helper rather than a literal request URI.
     *
     * @var array<string, string>
     */
    private const EXPLICIT_EVIDENCE = [
        'POST api/v1/webhooks/authorize-net' => 'tests/Feature/Domains/Payments/AuthorizeNetWebhookControllerTest.php',
        'POST api/v1/accounts/{account}/payments/sandbox-charges' => 'tests/Feature/Domains/Payments/SandboxChargeControllerTest.php',
        'POST api/v1/accounts/{account}/payments/attempts/{paymentAttempt}/sandbox-void' => 'tests/Feature/Domains/Payments/SandboxReversalControllerTest.php',
        'POST api/v1/accounts/{account}/payments/attempts/{paymentAttempt}/sandbox-refunds' => 'tests/Feature/Domains/Payments/SandboxReversalControllerTest.php',
        'POST api/v1/accounts/{account}/payments/attempts/{paymentAttempt}/sandbox-customer-profile' => 'tests/Feature/Domains/Payments/SandboxPaymentProfileControllerTest.php',
        'DELETE api/v1/accounts/{account}/callflow-integration-profiles/{profile}' => 'tests/Feature/Domains/CallRouting/CallflowIntegrationProfileControllerTest.php',
        'POST api/v1/accounts/{account}/sync/blacklists' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'GET api/v1/accounts/{account}/sync/blacklists/{run}' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'POST api/v1/accounts/{account}/sync/conferences' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'GET api/v1/accounts/{account}/sync/conferences/{run}' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'POST api/v1/accounts/{account}/sync/directories' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'GET api/v1/accounts/{account}/sync/directories/{run}' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'POST api/v1/accounts/{account}/sync/faxes' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'GET api/v1/accounts/{account}/sync/faxes/{run}' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'POST api/v1/accounts/{account}/sync/groups' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'GET api/v1/accounts/{account}/sync/groups/{run}' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'POST api/v1/accounts/{account}/sync/media' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'GET api/v1/accounts/{account}/sync/media/{run}' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'POST api/v1/accounts/{account}/sync/menus' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'GET api/v1/accounts/{account}/sync/menus/{run}' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'POST api/v1/accounts/{account}/sync/queues' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'GET api/v1/accounts/{account}/sync/queues/{run}' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'POST api/v1/accounts/{account}/sync/recordings' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
        'GET api/v1/accounts/{account}/sync/recordings/{run}' => 'tests/Feature/Domains/SwitchSynchronization/ProjectionSyncControllerTest.php',
    ];

    /**
     * Reviewed routes that still need a direct behavioral feature test.
     *
     * Removing a gap requires adding a request-level test first. The registry test will then
     * fail until the completed route is removed from this list.
     *
     * @var list<string>
     */
    private const REVIEWED_GAPS = [];

    public function test_route_to_feature_test_registry_matches_the_live_api_surface(): void
    {
        $routeKeys = $this->apiRoutes()
            ->map(fn (Route $route): string => $this->routeKey($route))
            ->sort()
            ->values()
            ->all();
        $evidence = [...$this->literalRequestEvidence(), ...self::EXPLICIT_EVIDENCE];

        foreach (self::EXPLICIT_EVIDENCE as $routeKey => $testFile) {
            $this->assertContains($routeKey, $routeKeys, "Explicit evidence references an unknown route: {$routeKey}");
            $this->assertFileExists(base_path($testFile));
        }

        $actualGaps = array_values(array_diff($routeKeys, array_keys($evidence)));
        $expectedGaps = self::REVIEWED_GAPS;
        sort($actualGaps);
        sort($expectedGaps);

        $this->assertSame(
            $expectedGaps,
            $actualGaps,
            "API feature coverage changed. Add request-level evidence or record the reviewed gap:\n".
                implode("\n", $actualGaps),
        );
    }

    /** @return Collection<int, Route> */
    private function apiRoutes(): Collection
    {
        return collect($this->app['router']->getRoutes()->getRoutes())
            ->filter(static fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
            ->values();
    }

    private function routeKey(Route $route): string
    {
        $methods = array_values(array_diff($route->methods(), ['HEAD']));

        return implode('|', $methods).' '.$route->uri();
    }

    /** @return array<string, string> */
    private function literalRequestEvidence(): array
    {
        $calls = $this->featureTestRequestCalls();
        $evidence = [];

        foreach ($this->apiRoutes() as $route) {
            $routeMethods = array_values(array_diff($route->methods(), ['HEAD']));
            $routePattern = $this->routePattern($route->uri());

            foreach ($calls as $call) {
                if (! in_array($call['method'], $routeMethods, true)
                    || preg_match($routePattern, $call['uri']) !== 1) {
                    continue;
                }

                $evidence[$this->routeKey($route)] = $call['test'];
                break;
            }
        }

        return $evidence;
    }

    /** @return list<array{method: string, uri: string, test: string}> */
    private function featureTestRequestCalls(): array
    {
        $methodMap = [
            'getJson' => 'GET',
            'postJson' => 'POST',
            'putJson' => 'PUT',
            'patchJson' => 'PATCH',
            'deleteJson' => 'DELETE',
            'get' => 'GET',
            'post' => 'POST',
            'put' => 'PUT',
            'patch' => 'PATCH',
            'delete' => 'DELETE',
        ];
        $calls = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('tests/Feature')));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            preg_match_all(
                '/->(getJson|postJson|putJson|patchJson|deleteJson|get|post|put|patch|delete)\s*\(\s*([\'\"])(\/api\/.*?)\2/s',
                $contents,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $calls[] = [
                    'method' => $methodMap[$match[1]],
                    'uri' => preg_replace('/\{\$[^}]+\}/', 'test-value', $match[3]) ?? $match[3],
                    'test' => str_replace(base_path().'/', '', $file->getPathname()),
                ];
            }
        }

        return $calls;
    }

    private function routePattern(string $uri): string
    {
        $quotedUri = preg_quote($uri, '#');
        $parameterizedUri = preg_replace('/\\\{[^}]+\\\}/', '[^/]+', $quotedUri) ?? $quotedUri;

        return '#^/'.$parameterizedUri.'(?:\?.*)?$#';
    }
}
