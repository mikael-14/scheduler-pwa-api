<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Delivery of a notification by email or push (notifications.mail_status / push_status).
 * No status (null) means that way was not used: turned off by the user, no device subscribed, or
 * (email) it waited for the summary and was read in the app before that went out.
 * Digest: waiting to go out in the person's next email summary (EmailDigest).
 */
enum DeliveryStatus: string implements HasLabel, HasColor, HasIcon
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Digest = 'digest';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Sent => __('Sent'),
            self::Failed => __('Failed'),
            self::Digest => __('In the next summary'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Digest => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Queued => 'heroicon-m-clock',
            self::Sent => 'heroicon-m-check-circle',
            self::Failed => 'heroicon-m-x-circle',
            self::Digest => 'heroicon-m-inbox-stack',
        };
    }
}
