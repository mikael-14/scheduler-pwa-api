<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Delivery of a notification by email or push (notifications.mail_status / push_status).
 * No status (null) means that way was not used: turned off by the user, or no device subscribed.
 */
enum DeliveryStatus: string implements HasLabel, HasColor, HasIcon
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Sent => __('Sent'),
            self::Failed => __('Failed'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Queued => 'heroicon-m-clock',
            self::Sent => 'heroicon-m-check-circle',
            self::Failed => 'heroicon-m-x-circle',
        };
    }
}
