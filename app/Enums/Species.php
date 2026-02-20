<?php

namespace App\Enums;

use Illuminate\Support\Facades\Lang;
use Filament\Support\Contracts\HasLabel;

interface DefaultValue
{
    public function default(): ?string;
}

enum Species: string implements HasLabel, DefaultValue
{
    const DefaultValue = 'cat';

    case Cat = 'cat';

    public function getLabel(): ?string
    {
        if (Lang::has("pet/species.{$this->name}")) {
            return __("pet/species.{$this->name}");
        }
        return $this->name;
    }
    public function default(): ?string {
    {
        return 'cat';
    }

    }
}

