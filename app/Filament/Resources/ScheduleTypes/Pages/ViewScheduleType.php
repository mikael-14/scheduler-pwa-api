<?php

namespace App\Filament\Resources\ScheduleTypes\Pages;

use App\Filament\Resources\ScheduleTypes\ScheduleTypeResource;
use App\Livewire\CalendarLegendWidget;
use App\Livewire\CalendarWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use App\Enums\ScheduleStatus;

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
            CalendarWidget::make([
                'record' => $this->record,
            ]),
            CalendarLegendWidget::make(),
        ];
    }
}
