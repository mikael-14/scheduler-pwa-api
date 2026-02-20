<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
 
enum DewormingType: string implements HasLabel
{
    case Internal = 'internal';
    case External = 'external';
    case InternalAndExternal = 'internal and external';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Internal => __('pet/deworming.internal'),
            self::External => __('pet/deworming.external'),
            self::InternalAndExternal => __('pet/deworming.internal_and_external'),
        };
    }
}