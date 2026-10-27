<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TodaySchedulesWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->can('view_any_schedule');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Today at the shelter'))
            ->description(now()->translatedFormat('l, d F Y'))
            ->query(fn(): Builder => Schedule::query()
                ->with(['user', 'schedule_type', 'schedule_users.user'])
                ->whereNotIn('status', [ScheduleStatus::Rejected, ScheduleStatus::Cancelled])
                ->where('start', '<=', now()->endOfDay())
                ->whereRaw('COALESCE(`end`, `start`) >= ?', [now()->startOfDay()])
                ->orderBy('start'))
            ->defaultSort('start')
            ->columns([
                ColorColumn::make('schedule_type.color')
                    ->label(''),
                TextColumn::make('schedule_type.name')
                    ->label(__('Type')),
                TextColumn::make('user.name')
                    ->label(__('Assigned To'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('schedule_users.user.name')
                    ->label(__('Participants'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('start')
                    ->label(__('Time'))
                    ->formatStateUsing(function ($state, Schedule $record) {
                        if ($record->all_day) {
                            return __('No time');
                        }

                        $time = config('app.time_format');

                        return $record->start->format($time)
                            . ($record->end ? ' – ' . $record->end->format($time) : '');
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->emptyStateHeading(__('Nobody is scheduled for today'))
            ->emptyStateIcon('heroicon-o-calendar')
            ->paginated([5, 10, 25]);
    }
}
