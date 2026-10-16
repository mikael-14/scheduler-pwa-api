<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Session\Middleware\StartSession;

Route::prefix('auth')->group(function () {
    // Email + password (throttled: these are the brute-force targets)
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');

    // Socialite providers
    Route::get('{provider}/redirect', [AuthController::class, 'redirectToProvider'])->middleware(StartSession::class);
    Route::get('{provider}/callback', [AuthController::class, 'handleProviderCallback'])->middleware(StartSession::class);

    // Logout (requires auth, allowed even while pending so the user can always sign out)
    Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);

});

// Push, notification list: only for active, approved accounts
Route::middleware(['auth:sanctum', 'active', 'approved'])->group(function () {
    // The public VAPID key the browser needs to create a push subscription
    Route::get('/pwa/vapid-public-key', fn() => response()->json([
        'key' => config('webpush.vapid.public_key'),
    ]));

    Route::post('/pwa/subscribe', function (Request $request) {
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

    Route::delete('/pwa/subscribe', function (Request $request) {
        $request->validate(['endpoint' => 'required']);

        $request->user()->deletePushSubscription($request->endpoint);

        return response()->json(['success' => true]);
    });

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
});

// Profile: available to pending accounts too, the PWA reads `approved_at` from here
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('user', [UserController::class, 'user']);
    Route::put('user', [UserController::class, 'update']);
    Route::patch('user', [UserController::class, 'patch']);
});
