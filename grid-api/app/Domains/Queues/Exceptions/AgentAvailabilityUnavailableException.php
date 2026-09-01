<?php

namespace App\Domains\Queues\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

class AgentAvailabilityUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Live agent availability is unavailable for this Switch deployment.');
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), Response::HTTP_CONFLICT);
    }
}
