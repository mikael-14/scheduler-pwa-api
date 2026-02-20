<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
 
enum MedicineType: string implements HasLabel
{
    case Pill = 'pill';
    case Capsule = 'capsule';
    case Liquid = 'liquid';
    case Injection = 'injection';
    case Toppical = 'toppical';
    case Inhaler = 'inhaler';
    case Suppository = 'suppository';
    case Drop = 'drop';
    case Powder = 'powder';

    public function getLabel(): ?string
    {
        return __("pet/medicine.{$this->name}");
    }

}