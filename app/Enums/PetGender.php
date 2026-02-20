<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
 
enum PetGender: string implements HasLabel, HasColor, HasIcon
{
    case Male = 'male';
    case Female = 'female';
    
    public function getLabel(): ?string
    {
        return __("pet/gender.{$this->name}");
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Male => 'blue',
            self::Female => 'rose',
            default => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Male => 'tabler-gender-male',
            self::Female => 'tabler-gender-female',
        };
    }
}