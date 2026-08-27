<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'store'])->middleware('guest')->name('login');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth');

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ready',
]));
