<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PwaResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot your password?" for the PWA, on Laravel's password broker (same tokens and expiry
 * as the admin panel's reset). Also how a Google / Facebook account sets a first password.
 */
class PasswordResetController extends Controller
{
    /**
     * Email a reset link. Always the same answer, so it cannot be used to find out which
     * emails have an account.
     */
    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Password::broker()->sendResetLink(
            ['email' => $data['email']],
            function (User $user, string $token) {
                // an inactive account cannot sign in anyway: no email
                if ($user->status) {
                    $user->notify(new PwaResetPassword($token));
                }
            }
        );

        return response()->json([
            'message' => __('If an account exists for this email, we sent a link to reset the password.'),
        ]);
    }

    /**
     * Set the new password. Every device signed in to the account is signed out.
     */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            // e.g. "This password reset token is invalid." (lang/pt/passwords.php)
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => __($status)]);
    }
}
