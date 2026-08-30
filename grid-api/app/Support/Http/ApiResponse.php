<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Builds the shared HTTP envelopes used by GridPBX API endpoints.
 *
 * Callers pass the domain value itself; this class owns the top-level `data`
 * key so controllers cannot accidentally create `data.data` responses.
 */
final class ApiResponse
{
    /** @param array<string, mixed> $meta */
    public static function data(
        mixed $data,
        int $status = Response::HTTP_OK,
        array $meta = [],
    ): JsonResponse {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /** @param array<string, mixed> $context */
    public static function error(
        string $message,
        int $status,
        array $context = [],
    ): JsonResponse {
        return response()->json(['message' => $message, ...$context], $status);
    }

    public static function noContent(): Response
    {
        return response()->noContent();
    }
}
