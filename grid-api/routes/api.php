<?php

use Illuminate\Http\Request;
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
        Route::get('/session', function (Request $request) {
            return response()->json([
                'data' => [
                    'user' => $request->user()?->load('organizations.kazooAccounts'),
                ],
            ]);
        });
    });
});
