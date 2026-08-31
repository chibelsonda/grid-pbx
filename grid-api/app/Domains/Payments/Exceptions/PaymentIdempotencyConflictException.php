<?php

namespace App\Domains\Payments\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PaymentIdempotencyConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('The idempotency key was already used for a different payment request.');
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), Response::HTTP_CONFLICT);
    }
}
