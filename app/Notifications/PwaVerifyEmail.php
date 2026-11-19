<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * "Confirm your email" for the PWA (sign-up with a password, or a new email in the profile).
 * The link opens the PWA's /verify-email screen with the values of a signed API URL; the PWA
 * posts them to POST /api/auth/email/verify/{id}/{hash} (EmailVerificationController).
 * The admin panel keeps its own verification email (Filament's), which links to the panel.
 * Not queued on purpose: the person is waiting for it right now.
 */
class PwaVerifyEmail extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** The PWA link: id, hash of the email, expiry and signature of the API route. */
    public static function urlFor(object $notifiable): string
    {
        $minutes = (int) config('auth.verification.expire', 60);

        // relative: the API checks it with hasValidRelativeSignature(), so a proxy or another
        // host name in front of the API cannot break it
        $signed = URL::temporarySignedRoute(
            'api.verification.verify',
            Carbon::now()->addMinutes($minutes),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            absolute: false,
        );

        parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

        return rtrim((string) config('app.pwa_url'), '/') . '/verify-email?' . http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $query['expires'] ?? '',
            'signature' => $query['signature'] ?? '',
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Confirm your email address'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Please confirm that this email address belongs to you.'))
            ->action(__('Confirm email'), static::urlFor($notifiable))
            ->line(__('This link expires in :count minutes.', ['count' => (int) config('auth.verification.expire', 60)]))
            ->line(__('If you did not create an account or change your email, you can ignore this email.'));
    }
}
