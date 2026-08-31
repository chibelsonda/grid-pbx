<?php

namespace App\Domains\Payments\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PaymentMutationUnavailableException extends RuntimeException implements ShouldntReport
{
    public function __construct(string $message = 'Sandbox payment processing is not available.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), Response::HTTP_CONFLICT);
    }
}
