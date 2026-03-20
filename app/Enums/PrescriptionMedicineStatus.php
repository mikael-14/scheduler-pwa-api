<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
 
enum PrescriptionMedicineStatus: string implements HasLabel, HasColor
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Canceled = 'canceled';
    case Completed = 'completed';
    
    public function getLabel(): ?string
    {
        return __("pet/measures.{$this->name}");
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Active => 'info',
            self::Completed => 'success',
            self::Canceled => 'danger',
            self::OnHold => 'warning',
            default => 'primary',
        };
    }
}