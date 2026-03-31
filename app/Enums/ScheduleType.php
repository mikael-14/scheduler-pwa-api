<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
 
enum ScheduleType: string implements HasLabel
{
    case Start = 'start';
    case End = 'end';
    case Range = 'range';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Start => __('pet/schedule.type.start'),
            self::End => __('pet/schedule.type.end'),
            self::Range => __('pet/schedule.type.range'),
        };
    }



}