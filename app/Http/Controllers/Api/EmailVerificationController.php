<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

/**
 * Email verification for the PWA. Accounts made with a password (and anyone who changes their
 * email) must confirm it before the rest of the API opens (EnsureUserIsApproved).
 * Google / Facebook sign-ups are verified by the provider and never come here.
 */
class EmailVerificationController extends Controller
{
    /**
     * The values of the emailed link (PwaVerifyEmail): a signed, expiring URL for this account
     * and the hash of its current email, so a link for an old address no longer works.
     * No sign-in needed: the signature is the proof.
     */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::find($id);

        if (! $request->hasValidRelativeSignature()
            || ! $user
            || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'message' => __('This verification link is invalid or has expired.'),
                'code' => 'invalid_link',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json([
            'message' => __('Your email address is confirmed.'),
            'verified' => true,
        ]);
    }

    /** Send the link again, to the signed-in account's current email. */
    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('Your email address is already confirmed.'), 'verified' => true]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('We sent a new confirmation link to :email.', ['email' => $user->email]), 'verified' => false]);
    }
}
