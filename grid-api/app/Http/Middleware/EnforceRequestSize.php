<?php

namespace App\Http\Middleware;

use App\Support\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceRequestSize
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $contentLength = $request->server('CONTENT_LENGTH');

        if (is_numeric($contentLength) && (int) $contentLength > $this->maximumBytes($request)) {
            return ApiResponse::error('Request payload is too large.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }

    private function maximumBytes(Request $request): int
    {
        if ($request->is('api/v1/webhooks/authorize-net')) {
            return max(1, (int) config('security.request_size.webhook_bytes'));
        }

        if (str_starts_with((string) $request->header('Content-Type'), 'multipart/form-data')) {
            return max(1, (int) config('security.request_size.upload_bytes'));
        }

        return max(1, (int) config('security.request_size.api_bytes'));
    }
}
