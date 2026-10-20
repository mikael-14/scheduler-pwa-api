<?php

namespace App\Notifications\Channels;

use App\Enums\DeliveryStatus;
use Illuminate\Notifications\Channels\DatabaseChannel as BaseDatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel's database channel (bound in its place in AppServiceProvider), so every stored
 * notification, the app's and Filament's, also records:
 * - sender_id: who caused it (the signed-in user; empty for the system, e.g. a scheduled task);
 * - mail_status = queued when the same notification also goes out by email.
 *
 * It runs inside the request (see AppNotification::viaConnections), so the signed-in user is known.
 */
class DatabaseChannel extends BaseDatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification)
    {
        $channels = method_exists($notification, 'via') ? (array) $notification->via($notifiable) : [];

        return parent::buildPayload($notifiable, $notification) + [
            'sender_id' => auth()->id(),
            'mail_status' => in_array('mail', $channels, true) ? DeliveryStatus::Queued->value : null,
        ];
    }
}
