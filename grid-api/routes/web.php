<?php

use App\Domains\IdentityAccess\Controllers\PasswordResetController;
use App\Domains\IdentityAccess\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SessionController::class, 'store'])->middleware('guest')->name('login');
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth');
Route::post('/forgot-password', [PasswordResetController::class, 'store'])
    ->middleware(['guest', 'throttle:forgot-password'])
    ->name('password.email');
Route::post('/reset-password', [PasswordResetController::class, 'update'])
    ->middleware(['guest', 'throttle:reset-password'])
    ->name('password.update');

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ready',
]));
