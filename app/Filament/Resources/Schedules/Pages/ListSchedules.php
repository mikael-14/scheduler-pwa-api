<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Schedules\ScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSchedules extends ListRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];

        // 1. Add the "All" tab first
        $tabs['all'] = Tab::make(__('All'));

        // 2. Loop through all Enum cases to generate status tabs
        foreach (ScheduleStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', $status->value))
                ->badge(fn() => $this->getModel()::where('status', $status->value)->count())
                ->badgeColor($status->getColor());
        }

        // 3. Optional: Add a custom "Mine" tab at the end
        $tabs['mine'] = Tab::make(__('Mine'))
            ->modifyQueryUsing(fn(Builder $query) => $query->where('user_id', auth()->id()))
            ->icon('heroicon-m-user');

        return $tabs;
    }
}
