<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CoveragePreviewWidget;
use App\Filament\Widgets\SentCoverageAlertsWidget;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Coverage alerts (config/coverage.php) without a terminal: what the daily check would do for a
 * day (a dry run: nothing is sent or recorded) and the summaries already sent. Answers "why did I
 * (not) get an alert?" on a host without SSH.
 *
 * Access: the Shield page permission `view_coverage_alerts` (Shield → Roles → Pages).
 */
class CoverageAlerts extends Page
{
    use HasPageShield;

    /** The permission Shield generates for this page ("view" + the class name, see AppServiceProvider). */
    public const PERMISSION = 'view_coverage_alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $slug = 'coverage-alerts';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('Coverage alerts');
    }

    public function getTitle(): string
    {
        return __('Coverage alerts');
    }

    /**
     * Only with the permission. Shield's own check lets everyone in when it cannot find the page's
     * permission; this one never does.
     */
    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can(self::PERMISSION);
    }

    public function getSubheading(): ?string
    {
        if (! config('coverage.enabled')) {
            return __('Off (COVERAGE_ALERTS=false): nothing is sent.');
        }

        return __('Checked every day at :time (:timezone).', [
            'time' => implode(', ', \App\Services\CoverageChecker::runTimes()),
            'timezone' => config('app.timezone'),
        ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CoveragePreviewWidget::class,
            SentCoverageAlertsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
