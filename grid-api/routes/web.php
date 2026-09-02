<?php

use App\Domains\IdentityAccess\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'store'])
    ->middleware(['guest', 'throttle:login'])
    ->name('login');
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth');

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ready',
]));
