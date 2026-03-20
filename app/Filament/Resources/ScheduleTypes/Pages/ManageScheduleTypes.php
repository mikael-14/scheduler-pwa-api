<?php

namespace App\Filament\Resources\ScheduleTypes\Pages;

use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageScheduleTypes extends ManageRecords
{
    protected static string $resource = ScheduleTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
