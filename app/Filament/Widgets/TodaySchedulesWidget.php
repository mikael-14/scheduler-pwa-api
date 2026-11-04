<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use App\Filament\Pages\ScheduleCalendar;
use App\Models\Schedule;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Today at the shelter, compact: when, what and who, the first few, with the day in the calendar
 * one click away.
 */
class TodaySchedulesWidget extends TableWidget
{
    private const ROWS = 8;

    protected int | string | array $columnSpan = ['default' => 'full', 'lg' => 1];

    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->can('view_any_schedule');
    }

    public function table(Table $table): Table
    {
        $visible = fn(): Builder => \App\Services\ScheduleAccess::scopeVisible(Schedule::query(), Filament::auth()->user());

        $today = fn(Builder $query) => $query
            ->whereNotIn('status', [ScheduleStatus::Rejected, ScheduleStatus::Cancelled])
            ->where('start', '<=', now()->endOfDay())
            ->whereRaw('COALESCE(`end`, `start`) >= ?', [now()->startOfDay()]);

        return $table
            ->heading(__('Today at the shelter'))
            ->description(now()->translatedFormat('l, d F'))
            // only what this person may see (ScheduleAccess::scopeVisible), like the calendar
            ->query(fn(): Builder => $today($visible())
                ->with(['user', 'schedule_type', 'schedule_users.user'])
                ->orderBy('all_day', 'desc')
                ->orderBy('start')
                ->limit(self::ROWS))
            ->headerActions([
                Action::make('calendar')
                    ->label(fn() => __('See all (:count)', ['count' => $today($visible())->count()]))
                    ->link()
                    ->url(ScheduleCalendar::getUrl(['date' => now()->toDateString()])),
            ])
            ->columns([
                TextColumn::make('start')
                    ->label(__('Time'))
                    ->formatStateUsing(function ($state, Schedule $record) {
                        if ($record->all_day) {
                            return __('No time');
                        }

                        $time = config('app.time_format');

                        return $record->start->format($time)
                            . ($record->end ? '–' . $record->end->format($time) : '');
                    }),
                ColorColumn::make('schedule_type.color')
                    ->label(''),
                TextColumn::make('schedule_type.name')
                    ->label(__('Type'))
                    // who: the owner and the people with them
                    ->description(function (Schedule $record) {
                        $names = collect([$record->user?->name])
                            ->merge($record->schedule_users->pluck('user.name'))
                            ->filter();

                        return $names->count() > 3
                            ? $names->take(3)->implode(', ') . ' +' . ($names->count() - 3)
                            : $names->implode(', ');
                    }),
                TextColumn::make('status')
                    ->label('')
                    ->badge(),
            ])
            ->emptyStateHeading(__('Nobody is scheduled for today'))
            ->emptyStateIcon('heroicon-o-calendar')
            ->paginated(false);
    }
}
