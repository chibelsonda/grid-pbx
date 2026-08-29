<?php

namespace Tests\Feature\Domains\IdentityAccess;

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class LocalSpaOriginTest extends TestCase
{
    public function test_local_vite_fallback_port_is_allowed_by_cors(): void
    {
        config([
            'cors.allowed_origins' => ['http://localhost:5173'],
            'cors.allowed_origins_patterns' => [
                '#^https?://(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$#',
            ],
        ]);

        $this->withHeaders([
            'Origin' => 'http://localhost:5174',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/session')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5174')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_local_vite_fallback_port_is_treated_as_a_stateful_spa(): void
    {
        config(['sanctum.stateful' => ['localhost:*', '127.0.0.1:*']]);
        $request = Request::create(
            '/api/v1/session',
            'GET',
            server: ['HTTP_ORIGIN' => 'http://localhost:5174'],
        );

        $this->assertTrue(EnsureFrontendRequestsAreStateful::fromFrontend($request));
    }
}
