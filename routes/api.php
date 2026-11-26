<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\UserController;

Route::prefix('auth')->group(function () {
    // Email + password (throttled: these are the brute-force targets). Limits in
    // AppServiceProvider::configureRateLimits: login per IP and per email, register per IP.
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');

    // Email verification (accounts made with a password, and after an email change).
    // The link in the email opens the PWA, which posts its signed values here: no sign-in needed,
    // so it also works when the email is opened on another device.
    Route::post('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->whereNumber('id')
        ->middleware('throttle:verification')
        ->name('api.verification.verify');
    Route::middleware(['auth:sanctum', 'active', 'throttle:verification'])
        ->post('email/resend', [EmailVerificationController::class, 'resend']);

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

// The browser replaced or dropped a device's push subscription (Chrome does this now and then):
// the app's service worker subscribes again and swaps it here, with nobody signed in (it cannot
// use the app's token). The old endpoint is the proof: a long random address only that device and
// this server know. Only for subscriptions of accounts that may still get push.
Route::post('/pwa/subscription/renew', function (Request $request) {
    $data = $request->validate([
        'old_endpoint' => 'required|string|max:2000',
        'endpoint'     => 'required|string|url|max:2000',
        'keys.auth'    => 'required|string|max:255',
        'keys.p256dh'  => 'required|string|max:255',
    ]);

    $old = app(config('webpush.model'))->findByEndpoint($data['old_endpoint']);
    $user = $old?->subscribable;
    if (! $user instanceof \App\Models\User || ! $user->status || ! $user->approved_at) {
        return response()->json(['message' => 'Unknown subscription.'], 404);
    }

    $user->updatePushSubscription($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth']);
    if ($data['endpoint'] !== $data['old_endpoint']) {
        $old->delete();
    }

    return response()->json(['success' => true]);
})->middleware('throttle:10,1');

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
    Route::post('/notifications/read', [NotificationController::class, 'readMany']);
    Route::post('/notifications/unread', [NotificationController::class, 'unreadMany']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
});

// Profile: available to pending accounts too, the PWA reads `approved_at` from here
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('user', [UserController::class, 'user']);
    // an email change sends emails: same limit as the other changes
    Route::put('user', [UserController::class, 'update'])->middleware('throttle:writes');
    Route::patch('user', [UserController::class, 'patch'])->middleware('throttle:writes');

    // Profile photo
    Route::post('user/avatar', [UserController::class, 'uploadAvatar'])->middleware('throttle:10,1');
    Route::delete('user/avatar', [UserController::class, 'deleteAvatar']);

    // Signed-in devices (one API token each)
    Route::get('user/sessions', [UserController::class, 'sessions']);
    Route::delete('user/sessions', [UserController::class, 'revokeOtherSessions']);
    Route::delete('user/sessions/{id}', [UserController::class, 'revokeSession'])->whereNumber('id');
});
