<?php

namespace App\Filament\Resources\ScheduleTypes\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Schema;
use Symfony\Component\Console\Color;

class ScheduleTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FusedGroup::make([
                    TextInput::make('name')
                        ->placeholder('name'),
                    ColorPicker::make('color')
                        ->placeholder('Select a color'),
                ]),
                DateTimePicker::make('start')
                    ->placeholder('Start Date'),
                DateTimePicker::make('end')
                    ->placeholder('End Date'),
            ]);
    }
}
