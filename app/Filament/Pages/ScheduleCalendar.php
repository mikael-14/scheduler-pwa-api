<?php

namespace App\Filament\Pages;

use App\Livewire\CalendarLegendWidget;
use App\Livewire\CalendarWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ScheduleCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::CalendarDateRange;

    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'schedule-calendar';

    public static function getNavigationGroup(): ?string
    {
        return __('Scheduler');
    }

    public static function getNavigationLabel(): string
    {
        return __('Calendar');
    }

    public function getTitle(): string
    {
        return __('Calendar');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_any_schedule');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CalendarLegendWidget::make(['showTypeFilter' => true]),
            CalendarWidget::make(),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
