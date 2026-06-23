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
        return [
            'upcoming' => Tab::make('Upcoming')
                ->label(__('Upcoming'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('start', '>=', now()->startOfDay()))
                ->icon('heroicon-m-calendar-days'),

            'past' => Tab::make('Past')
                ->label(__('Past'))
                ->modifyQueryUsing(fn(Builder $query) => $query->where('start', '<', now()->startOfDay()))
                ->icon('heroicon-m-clock'),

            'all' => Tab::make('All History')
                ->label(__('All History')),
        ];
    }

    // Add this to make 'Upcoming' the default when the page opens
    public function getDefaultActiveTab(): string | int | null
    {
        return 'upcoming';
    }
}
