<?php

use App\Domains\Extensions\Presentation\Http\Controllers\ExtensionController;
use App\Domains\IdentityAccess\Presentation\Http\Controllers\SessionController;
use App\Domains\KazooSynchronization\Presentation\Http\Controllers\ExtensionSyncController;
use App\Domains\Organizations\Presentation\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'data' => [
            'service' => 'grid-api',
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ],
    ]));

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/session', [SessionController::class, 'show']);
        Route::get('/accounts', AccountController::class);
        Route::get('/accounts/{account}/extensions', ExtensionController::class);
        Route::post('/accounts/{account}/sync/extensions', [ExtensionSyncController::class, 'store']);
        Route::get('/accounts/{account}/sync/extensions/{run}', [ExtensionSyncController::class, 'show']);
    });
});
