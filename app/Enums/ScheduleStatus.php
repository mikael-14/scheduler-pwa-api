<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasDescription;

enum ScheduleStatus: string implements HasLabel, HasColor, HasDescription
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NotShown = 'not_shown';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => __('pet/schedule.status.pending'),
            self::Approved => __('pet/schedule.status.approved'),
            self::Rejected => __('pet/schedule.status.rejected'),
            self::Cancelled => __('pet/schedule.status.cancelled'),
            self::Completed => __('pet/schedule.status.completed'),
            self::NotShown => __('pet/schedule.status.not_shown'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'danger',
            self::Completed => 'primary',
            self::NotShown => 'info',
            default => 'info'
        };
    }
    public function getDescription(): string | null
    {
        return match ($this) {
            self::Pending => __('pet/schedule.description.pending'),
            self::Approved => __('pet/schedule.description.approved'),
            self::Rejected => __('pet/schedule.description.rejected'),
            self::Cancelled => __('pet/schedule.description.cancelled'),
            self::Completed => __('pet/schedule.description.completed'),
            self::NotShown => __('pet/schedule.description.not_shown'),
        };
    }
}
