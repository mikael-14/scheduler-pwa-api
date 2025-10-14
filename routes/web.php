<?php

use Illuminate\Support\Facades\Route;


use App\Http\Controllers\Api\AuthController;

Route::prefix('auth')->group(function () {
    // Email + password
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    // Socialite providers
    Route::get('{provider}/redirect', [AuthController::class, 'redirectToProvider']);
    Route::get('{provider}/callback', [AuthController::class, 'handleProviderCallback']);

    // Logout (requires auth)
    Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);
});

Route::get('/', function () {
    return view('welcome');
});
