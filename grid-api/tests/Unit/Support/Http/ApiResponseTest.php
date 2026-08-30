<?php

namespace Tests\Unit\Support\Http;

use App\Support\Http\ApiResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    #[Test]
    public function it_wraps_domain_data_once_and_preserves_optional_meta_and_status(): void
    {
        $response = ApiResponse::data(
            ['editor' => true],
            Response::HTTP_ACCEPTED,
            ['request_id' => 'request-1'],
        );

        $this->assertSame(Response::HTTP_ACCEPTED, $response->status());
        $this->assertSame([
            'data' => ['editor' => true],
            'meta' => ['request_id' => 'request-1'],
        ], $response->getData(true));
    }

    #[Test]
    public function it_builds_error_and_empty_responses_without_a_data_envelope(): void
    {
        $error = ApiResponse::error('Switch unavailable.', Response::HTTP_BAD_GATEWAY, [
            'code' => 'switch_unavailable',
        ]);

        $this->assertSame([
            'message' => 'Switch unavailable.',
            'code' => 'switch_unavailable',
        ], $error->getData(true));
        $this->assertSame(Response::HTTP_NO_CONTENT, ApiResponse::noContent()->status());
    }
}
