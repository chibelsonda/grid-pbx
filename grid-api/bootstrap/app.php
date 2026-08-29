<?php

use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api/*') ? null : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (SwitchRequestException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if (in_array($exception->statusCode, [400, 409, 422], true)) {
                return response()->json([
                    'message' => 'Switch rejected the submitted configuration.',
                ], 422);
            }

            if ($exception->statusCode === 404) {
                return response()->json([
                    'message' => 'The Switch resource is no longer available. Synchronize and try again.',
                ], 409);
            }

            return response()->json([
                'message' => 'Switch is unavailable. Try again later.',
            ], 502);
        });
    })->create();
