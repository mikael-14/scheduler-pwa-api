<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
 
enum ScheduleType: string implements HasLabel
{
    case StartOnly = 'start_only';
    case StartAndEnd = 'start_and_end';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::StartOnly => __('pet/schedule.type.start_only'),
            self::StartAndEnd => __('pet/schedule.type.start_and_end'),
        };
    }



}