<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\UserController;

Route::prefix('auth')->group(function () {
    // Email + password (throttled: these are the brute-force targets)
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');

    // Forgot password: email a link to the PWA's reset screen, then set the new password
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');

    // Socialite providers
    // Stateless (no session): the OAuth state lives in the cache, see AuthController
    Route::get('{provider}/redirect', [AuthController::class, 'redirectToProvider'])->middleware('throttle:20,1');
    Route::get('{provider}/callback', [AuthController::class, 'handleProviderCallback'])->middleware('throttle:20,1');

    // Logout (requires auth, allowed even while pending so the user can always sign out)
    Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);

    // Renew the token while the app is in use (pending accounts too, so their session lasts)
    Route::middleware(['auth:sanctum', 'active', 'throttle:10,1'])->post('refresh', [AuthController::class, 'refresh']);

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

    // Profile photo
    Route::post('user/avatar', [UserController::class, 'uploadAvatar'])->middleware('throttle:10,1');
    Route::delete('user/avatar', [UserController::class, 'deleteAvatar']);

    // Signed-in devices (one API token each)
    Route::get('user/sessions', [UserController::class, 'sessions']);
    Route::delete('user/sessions', [UserController::class, 'revokeOtherSessions']);
    Route::delete('user/sessions/{id}', [UserController::class, 'revokeSession'])->whereNumber('id');
});
