<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
 
enum PersonFlag: string implements HasLabel, HasColor
{
    case Cleaning_volunteer = 'cleaning_volunteer';
    case Driver_volunteer = 'driver_volunteer';
    case Medication_volunteer =  'medication_volunteer';
    case Temporary_family = 'temporary_family';
    case Veterinary = 'veterinary';
    case Adopter = 'adopter';
    case Sponsor = 'sponsor';
    case Black_list = 'black_list';
    
    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cleaning_volunteer => __('pet/personflag.cleaning_volunteer'),
            self::Driver_volunteer => __('pet/personflag.driver_volunteer'),
            self::Medication_volunteer => __('pet/personflag.medication_volunteer'),
            self::Temporary_family => __('pet/personflag.temporary_family'),
            self::Veterinary => __('pet/personflag.veterinary'),
            self::Adopter => __('pet/personflag.adopter'),
            self::Sponsor => __('pet/personflag.sponsor'),
            self::Black_list => __('pet/personflag.black_list'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Temporary_family => 'warning',
            self::Sponsor => 'info',
            self::Adopter => 'info',
            self::Black_list => 'danger',
            self::Veterinary => 'success',
            default => 'primary',
        };
    }
}