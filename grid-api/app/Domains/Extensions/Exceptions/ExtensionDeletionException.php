<?php

namespace App\Domains\Extensions\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExtensionDeletionException extends RuntimeException
{
    /** @param list<string> $completedSteps */
    public function __construct(
        public readonly string $operationId,
        public readonly array $completedSteps,
        Throwable $previous,
    ) {
        parent::__construct(
            $completedSteps === []
                ? 'Extension deletion failed before any managed resource was removed.'
                : 'Extension deletion is incomplete and requires a retry or reconciliation.',
            0,
            $previous,
        );
    }

    public function repairRequired(): bool
    {
        return $this->completedSteps !== [];
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->repairRequired()
                ? 'extension_repair_required'
                : 'extension_deletion_failed',
            'repair_required' => $this->repairRequired(),
            'operation_id' => $this->operationId,
        ], Response::HTTP_BAD_GATEWAY);
    }
}
