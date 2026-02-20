<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
 
enum PersonGender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';
    case Undefined = 'undefined';

    public function getLabel(): ?string
    {
        return __("pet/persongender.{$this->name}");
    }

   
}