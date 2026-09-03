<?php

use App\Domains\SwitchSynchronization\Commands\PollExtensionProjectionsCommand;
use App\Support\Http\ApiResponse;
use App\Support\Http\SwitchExceptionResponseFactory;
use GridPbx\Switch\Shared\Exceptions\SwitchRequestException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        PollExtensionProjectionsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api/*') ? null : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /** @var WeakMap<Throwable, string> $errorReferences */
        $errorReferences = new WeakMap;

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->context(function (Throwable $exception) use ($errorReferences): array {
            $errorReferences[$exception] ??= (string) Str::uuid();

            return ['error_id' => $errorReferences[$exception]];
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')
                || ! $exception->getPrevious() instanceof ModelNotFoundException) {
                return null;
            }

            return ApiResponse::error(
                'The requested resource was not found.',
                SymfonyResponse::HTTP_NOT_FOUND,
            );
        });

        $exceptions->render(function (SwitchRequestException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return app(SwitchExceptionResponseFactory::class)->make($exception, $request);
        });

        $exceptions->respond(function (
            SymfonyResponse $response,
            Throwable $exception,
            Request $request,
        ) use ($errorReferences): SymfonyResponse {
            if (! $request->is('api/*')
                || $response->getStatusCode() < SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR
                || $exception instanceof SwitchRequestException
                || method_exists($exception, 'render')) {
                return $response;
            }

            $errorReferences[$exception] ??= (string) Str::uuid();

            return ApiResponse::error(
                'An unexpected server error occurred. Try again. If the problem continues, contact support.',
                $response->getStatusCode(),
                ['error_id' => $errorReferences[$exception]],
            );
        });
    })->create();
