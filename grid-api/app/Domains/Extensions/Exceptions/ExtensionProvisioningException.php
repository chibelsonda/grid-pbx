<?php

namespace App\Domains\Extensions\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExtensionProvisioningException extends RuntimeException
{
    /** @param list<string> $compensationFailures */
    public function __construct(
        public readonly string $operationId,
        public readonly array $compensationFailures,
        Throwable $previous,
    ) {
        parent::__construct(
            $compensationFailures === []
                ? 'Extension provisioning failed and created resources were removed.'
                : 'Extension provisioning failed and automatic cleanup is incomplete.',
            0,
            $previous,
        );
    }

    public function repairRequired(): bool
    {
        return $this->compensationFailures !== [];
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), Response::HTTP_BAD_GATEWAY, [
            'code' => $this->repairRequired()
                ? 'extension_repair_required'
                : 'extension_provisioning_failed',
            'repair_required' => $this->repairRequired(),
            'operation_id' => $this->operationId,
        ]);
    }
}
