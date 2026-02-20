<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
 
enum PrescriptionMedicineStatus: string implements HasLabel, HasColor
{
    case active = 'active';
    case onhold = 'on_hold';
    case canceled = 'canceled';
    case completed = 'completed';
    
    public function getLabel(): ?string
    {
        return __("pet/measures.{$this->name}");
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::active => 'info',
            self::completed => 'success',
            self::canceled => 'danger',
            self::onhold => 'warning',
            default => 'primary',
        };
    }
}