<?php

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ApiExceptionSanitizationTest extends TestCase
{
    public function test_unexpected_api_exception_does_not_expose_backend_details(): void
    {
        Route::get('/api/_test/unexpected-error', static function (): never {
            throw new RuntimeException(
                'SQLSTATE[23000]: update switch_accounts set password = super-secret with bindings and trace',
            );
        });

        $response = $this->getJson('/api/_test/unexpected-error');

        $response->assertInternalServerError()
            ->assertExactJson([
                'message' => 'An unexpected server error occurred. Try again. If the problem continues, contact support.',
                'error_id' => $response->json('error_id'),
            ])
            ->assertDontSee('SQLSTATE', false)
            ->assertDontSee('super-secret', false)
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');

        $this->assertTrue(Str::isUuid($response->json('error_id')));
    }

    public function test_intentional_client_error_keeps_its_actionable_message(): void
    {
        Route::get('/api/_test/intentional-error', static function (): never {
            throw new ConflictHttpException('Refresh the candidate list and try again.');
        });

        $this->getJson('/api/_test/intentional-error')
            ->assertConflict()
            ->assertJsonPath('message', 'Refresh the candidate list and try again.');
    }
}
