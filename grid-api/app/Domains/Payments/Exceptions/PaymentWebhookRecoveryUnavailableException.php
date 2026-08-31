<?php

namespace App\Domains\Payments\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookRecoveryUnavailableException extends RuntimeException implements ShouldntReport
{
    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), Response::HTTP_CONFLICT);
    }
}
