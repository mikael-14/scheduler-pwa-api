<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Session\Middleware\StartSession; 

Route::prefix('auth')->group(function () {
    // Email + password
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    // Socialite providers
    Route::get('{provider}/redirect', [AuthController::class, 'redirectToProvider'])->middleware(StartSession::class);
    Route::get('{provider}/callback', [AuthController::class, 'handleProviderCallback'])->middleware(StartSession::class);
    Route::middleware('auth:sanctum')->get('user', [AuthController::class, 'user']);
    // Logout (requires auth)
    Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);

});

Route::middleware('auth:sanctum')->post('/pwa/subscribe', function (Request $request) {
    $request->validate([
        'endpoint'    => 'required',
        'keys.auth'   => 'required',
        'keys.p256dh' => 'required'
    ]);

    // This updates or creates the subscription for the logged-in user
    $request->user()->updatePushSubscription(
        $request->endpoint,
        $request->keys['p256dh'],
        $request->keys['auth']
    );

    return response()->json(['success' => true]);
});

Route::middleware('auth:sanctum')->put('user', [UserController::class, 'update']);
Route::middleware('auth:sanctum')->patch('user', [UserController::class, 'patch']);