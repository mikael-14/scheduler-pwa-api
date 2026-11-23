<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CoverageAlerts;
use App\Models\SentCoverageAlert;
use App\Notifications\CoverageAlert;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Coverage alerts page: the summaries already sent, one row per day they listed (the
 * coverage_alerts table), newest first. Who received each one is in Administration → Notifications.
 */
class SentCoverageAlertsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return CoverageAlerts::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Alerts sent'))
            ->description(__('One row per day listed in a summary. Who received them: Administration → Notifications.'))
            ->query(SentCoverageAlert::query()->with('scheduleType'))
            ->defaultSort(fn(Builder $query) => $query->orderByDesc('sent_on')->orderByDesc('run_at')->orderBy('level', 'desc')->orderBy('date'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Sent'))
                    ->dateTime(config('app.date_time_format'))
                    ->sortable(),
                TextColumn::make('run_at')
                    ->label(__('Run'))
                    ->alignCenter(),
                TextColumn::make('scheduleType.name')
                    ->label(__('Type'))
                    ->placeholder(fn(SentCoverageAlert $record) => $record->rule),
                TextColumn::make('level')
                    ->label(__('Summary'))
                    ->badge()
                    ->formatStateUsing(fn(string $state) => CoveragePreviewWidget::levelLabel($state))
                    ->color(fn(string $state) => $state === CoverageAlert::URGENT ? 'danger' : 'warning'),
                TextColumn::make('date')
                    ->label(__('Day'))
                    ->formatStateUsing(fn($state) => $state->locale(app()->getLocale())->translatedFormat(__('l, j F')))
                    ->sortable(),
                TextColumn::make('days_before')
                    ->label(__('Days ahead'))
                    ->alignCenter(),
                TextColumn::make('approved_count')
                    ->label(__('Approved'))
                    ->alignCenter(),
                TextColumn::make('pending_count')
                    ->label(__('Pending'))
                    ->alignCenter(),
                TextColumn::make('recipients')
                    ->label(__('Recipients'))
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->label(__('Summary'))
                    ->options([
                        CoverageAlert::URGENT => __('Urgent'),
                        CoverageAlert::AHEAD => __('Look-ahead'),
                    ]),
                SelectFilter::make('run_at')
                    ->label(__('Run'))
                    ->options(fn() => array_combine(\App\Services\CoverageChecker::runTimes(), \App\Services\CoverageChecker::runTimes())),
                SelectFilter::make('rule')
                    ->label(__('Rule'))
                    ->options(fn() => SentCoverageAlert::query()->distinct()->orderBy('rule')->pluck('rule', 'rule')->all()),
            ])
            ->emptyStateHeading(__('No coverage alert sent yet'))
            ->paginated([10, 25, 50]);
    }
}
