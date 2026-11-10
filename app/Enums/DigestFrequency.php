<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How someone gets the emails that can wait (users.email_digest, see AppNotification::digestible):
 * in a summary (App\Notifications\EmailDigest, sent by `notifications:digest`) or each one right away.
 * Stored as a plain string column, so adding a value needs no migration.
 */
enum DigestFrequency: string implements HasLabel
{
    // in the order the settings offer them
    case Twice = 'twice';
    case Daily = 'daily';
    case Instant = 'instant';

    /** The default for new and existing accounts. */
    public const DEFAULT = self::Twice;

    public function getLabel(): string
    {
        return match ($this) {
            self::Twice => __('Summary twice a day (08:00 and 18:00)'),
            self::Daily => __('Summary once a day (08:00)'),
            self::Instant => __('Each email right away'),
        };
    }

    /** The emails that can wait go into a summary instead of going out one by one. */
    public function isSummary(): bool
    {
        return $this !== self::Instant;
    }

    /** Only in the morning run (`notifications:digest --with-daily`). */
    public function onlyInTheMorning(): bool
    {
        return $this === self::Daily;
    }
}
