<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CoverageAlerts;
use App\Notifications\CoverageAlert;
use App\Services\CoverageChecker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * The Coverage alerts page: what `coverage:check` does for a day, as a dry run (nothing is sent
 * or recorded). Each day checked, the summary it belongs to and why it is (not) in one: covered,
 * would send, already sent, outside the type's dates...
 */
class CoveragePreviewWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return CoverageAlerts::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Check preview'))
            ->description(fn() => $this->settings())
            ->records(fn(?array $filters) => $this->rows($filters['day']['day'] ?? null, $filters['day']['at'] ?? null))
            ->paginated(false)
            ->filters([
                // the whole row: the date and the run side by side, each wide enough to read
                Filter::make('day')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->schema([
                        DatePicker::make('day')
                            ->label(__('As if today were'))
                            ->default(fn() => now(config('app.timezone'))->toDateString())
                            ->native(false)
                            ->displayFormat(config('app.date_format')),
                        // which daily run: by default the latest one already reached
                        Select::make('at')
                            ->label(__('Run'))
                            ->options(fn() => array_combine(CoverageChecker::runTimes(), CoverageChecker::runTimes()))
                            ->default(fn() => $this->defaultRun())
                            ->selectablePlaceholder(false),
                    ]),
            ], layout: FiltersLayout::AboveContent)
            // a dry run is cheap: show the new day or run at once, no "Apply filters" button
            ->deferFilters(false)
            ->columns([
                TextColumn::make('type')
                    ->label(__('Type')),
                TextColumn::make('date')
                    ->label(__('Day'))
                    ->formatStateUsing(fn(string $state) => Carbon::parse($state)->locale(app()->getLocale())->translatedFormat(__('l, j F'))),
                TextColumn::make('days_before')
                    ->label(__('Days ahead'))
                    ->alignCenter(),
                TextColumn::make('level')
                    ->label(__('Summary'))
                    ->badge()
                    ->formatStateUsing(fn(string $state) => self::levelLabel($state))
                    ->color(fn(string $state) => $state === CoverageAlert::URGENT ? 'danger' : 'warning'),
                TextColumn::make('approved')
                    ->label(__('Approved'))
                    ->alignCenter(),
                TextColumn::make('pending')
                    ->label(__('Pending'))
                    ->alignCenter(),
                TextColumn::make('result')
                    ->label(__('Result'))
                    ->badge()
                    ->formatStateUsing(fn(string $state) => self::resultLabel($state))
                    ->color(fn(string $state) => match ($state) {
                        'covered' => 'success',
                        'would send' => 'danger',
                        'already sent' => 'info',
                        'type not found' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('recipients')
                    ->label(__('Recipients'))
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => $state ?: '')
                    ->placeholder(''),
            ])
            ->emptyStateHeading(__('No coverage rules in config/coverage.php.'));
    }

    /** One row per rule and day checked, soonest first; a dry run never sends or records anything. */
    private function rows(?string $day, ?string $at): array
    {
        $today = Carbon::parse($day ?: now(config('app.timezone'))->toDateString(), config('app.timezone'));
        $at = in_array($at, CoverageChecker::runTimes(), true) ? $at : $this->defaultRun();

        $rows = app(CoverageChecker::class)->run($today, dryRun: true, at: $at);

        return collect($rows)
            ->mapWithKeys(fn(array $row) => [$row['rule'] . '|' . $row['date'] => $row + ['key' => $row['rule'] . '|' . $row['date']]])
            ->all();
    }

    /** The latest run already reached today, or the first one before it. */
    private function defaultRun(): string
    {
        $at = CoverageChecker::runAt(now(config('app.timezone')));

        return in_array($at, CoverageChecker::runTimes(), true) ? $at : CoverageChecker::runTimes()[0];
    }

    /** "Abrigo: every day · at least 1 approved · 7 days ahead, 1 urgent · everyone" per rule. */
    private function settings(): HtmlString
    {
        $checker = app(CoverageChecker::class);

        return collect($checker->rules())
            ->map(function (array $rule) {
                $weekdays = array_map('intval', $rule['weekdays'] ?? []);
                sort($weekdays);
                $days = $weekdays === [1, 2, 3, 4, 5, 6, 7]
                    ? __('every day')
                    : collect($weekdays)->map(fn(int $day) => Carbon::create(2024, 1, $day)->locale(app()->getLocale())->translatedFormat('D'))->implode(', ');
                $audience = is_array($rule['audience'] ?? null) && ! empty($rule['audience']['roles'])
                    ? implode(', ', $rule['audience']['roles'])
                    : __('everyone');

                return ($rule['type'] ?? '?') . ': ' . implode(' · ', [
                    $days,
                    __('at least :count approved', ['count' => $rule['min_approved'] ?? 1]),
                    __(':days days ahead, :urgent urgent', ['days' => $rule['look_ahead_days'] ?? 0, 'urgent' => $rule['urgent_days'] ?? 0]),
                    $audience,
                ]);
            })
            ->map(fn(string $line) => e($line))
            ->pipe(fn($lines) => new HtmlString($lines->implode('<br>')));
    }

    public static function levelLabel(string $level): string
    {
        return $level === CoverageAlert::URGENT ? __('Urgent') : __('Look-ahead');
    }

    private static function resultLabel(string $result): string
    {
        return match ($result) {
            'covered' => __('Covered'),
            'would send' => __('Would send'),
            'already sent' => __('Already sent'),
            'weekday not checked' => __('Weekday not checked'),
            'outside the type\'s dates' => __('Outside the type\'s dates'),
            'type inactive' => __('Type inactive'),
            'type not found' => __('Type not found'),
            default => $result,
        };
    }
}
