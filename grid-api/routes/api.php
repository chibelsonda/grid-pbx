<?php

use App\Domains\Devices\Controllers\DeviceController;
use App\Domains\Extensions\Controllers\ExtensionController;
use App\Domains\Extensions\Controllers\ExtensionDetailController;
use App\Domains\IdentityAccess\Controllers\SessionController;
use App\Domains\Organizations\Controllers\AccountController;
use App\Domains\SwitchSynchronization\Controllers\ExtensionSyncController;
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
        Route::get('/accounts/{account}/extensions/{extension}', ExtensionDetailController::class);
        Route::get('/accounts/{account}/devices', [DeviceController::class, 'index']);
        Route::post('/accounts/{account}/devices', [DeviceController::class, 'store']);
        Route::get('/accounts/{account}/devices/{device}', [DeviceController::class, 'show']);
        Route::put('/accounts/{account}/devices/{device}', [DeviceController::class, 'update']);
        Route::delete('/accounts/{account}/devices/{device}', [DeviceController::class, 'destroy']);
        Route::post('/accounts/{account}/sync/extensions', [ExtensionSyncController::class, 'store']);
        Route::get('/accounts/{account}/sync/extensions/{run}', [ExtensionSyncController::class, 'show']);
    });
});
