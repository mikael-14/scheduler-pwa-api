<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
 
enum PetMeasure: string implements HasLabel
{
    case weight = 'weight';
    
    public function getLabel(): ?string
    {
        return __("pet/measures.{$this->name}");
    }

    public static function getUnit($value): ?string
    {
        return match ($value) {
            self::weight => 'Kg',
            default => '',
        };
    }
    public static function getVariation($value): ?float {
        return match ($value) {
            self::weight => 0.099,
            default => null,
        };
    }
}