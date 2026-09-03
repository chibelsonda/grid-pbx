<?php

namespace Tests\Feature\Support;

use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
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

    public function test_switch_callflow_number_conflict_returns_safe_actionable_field_error(): void
    {
        Route::post('/api/_test/callflows', static function (): never {
            throw new SwitchRequestException('Switch request failed.', 400, [
                'data' => [
                    'numbers' => [
                        'unique' => [
                            'message' => '1234 exists in callflow 36756f7dd9b32f82997d30122891ecc8 (private-name)',
                        ],
                    ],
                ],
                'message' => 'invalid data',
                'request_id' => 'private-switch-request-id',
            ]);
        });

        $this->postJson('/api/_test/callflows', [
            'extension_numbers' => ['1234'],
        ])->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Extension 1234 is already assigned to another callflow.',
                'code' => 'callflow_number_conflict',
                'errors' => [
                    'extension_numbers' => [
                        'Extension 1234 is already assigned to another callflow.',
                    ],
                ],
            ])
            ->assertDontSee('36756f7dd9b32f82997d30122891ecc8')
            ->assertDontSee('private-name')
            ->assertDontSee('private-switch-request-id');
    }

    public function test_unknown_switch_validation_payload_remains_private(): void
    {
        Route::post('/api/_test/devices', static function (): never {
            throw new SwitchRequestException('Switch request failed.', 422, [
                'data' => ['private_token' => ['message' => 'secret-value']],
                'request_id' => 'private-switch-request-id',
            ]);
        });

        $this->postJson('/api/_test/devices', ['name' => 'Reception phone'])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Switch rejected the submitted configuration.',
                'code' => 'switch_configuration_rejected',
            ])
            ->assertDontSee('secret-value')
            ->assertDontSee('private_token')
            ->assertDontSee('private-switch-request-id');
    }

    public function test_structured_switch_validation_is_translated_for_other_entities(): void
    {
        Route::post('/api/_test/devices', static function (): never {
            throw new SwitchRequestException('Switch request failed.', 400, [
                'data' => [
                    'name' => [
                        'required' => [
                            'message' => 'Raw upstream detail with private-resource-id.',
                        ],
                    ],
                ],
                'request_id' => 'private-switch-request-id',
            ]);
        });

        $this->postJson('/api/_test/devices', ['name' => null])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Switch requires a value for name.',
                'code' => 'switch_validation_failed',
                'errors' => [
                    'name' => ['Switch requires a value for name.'],
                ],
            ])
            ->assertDontSee('private-resource-id')
            ->assertDontSee('private-switch-request-id');
    }
}
