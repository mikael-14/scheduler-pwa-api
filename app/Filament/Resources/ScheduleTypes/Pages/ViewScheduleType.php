<?php

namespace App\Filament\Resources\ScheduleTypes\Pages;

use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;
use App\Livewire\CalendarWidget;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Support\Enums\MaxWidth;

class ViewScheduleType extends ViewRecord
{
    protected static string $resource = ScheduleTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
    protected function getHeaderWidgets(): array
    {
        return [
            FilamentInfoWidget::make(),
            CalendarWidget::make([
                'record' => $this->record,
            ]),
        ];
    }

}
