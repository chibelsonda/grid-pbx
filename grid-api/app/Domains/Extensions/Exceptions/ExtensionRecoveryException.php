<?php

namespace App\Domains\Extensions\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExtensionRecoveryException extends RuntimeException
{
    public function __construct(public readonly string $operationId, Throwable $previous)
    {
        parent::__construct('The extension recovery action failed and can be retried.', 0, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'extension_recovery_failed',
            'repair_required' => true,
            'operation_id' => $this->operationId,
        ], Response::HTTP_BAD_GATEWAY);
    }
}
