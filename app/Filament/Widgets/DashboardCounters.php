<?php

namespace App\Filament\Widgets;

use App\Enums\ScheduleStatus;
use App\Filament\Pages\Approvals;
use App\Filament\Pages\ScheduleCalendar;
use App\Filament\Resources\Users\UserResource;
use App\Models\Schedule;
use App\Models\User;
use App\Services\AgendaSummary;
use App\Services\ScheduleAccess;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The top row of the dashboard: what needs someone (users and schedules waiting for approval)
 * and how busy the week and today are, each linking to where it is dealt with. Each counter only
 * for who may act on it.
 */
class DashboardCounters extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return (bool) ($user?->can('approve_user') || $user?->can('view_any_schedule') || ($user && ScheduleAccess::canApprove($user)));
    }

    /**
     * Every row full, whatever the number of counters (each person sees only theirs): one under
     * the other on phones, then all in one row (4: two rows of two first, then one row from lg).
     */
    protected function getColumns(): int | array
    {
        $count = max(1, count($this->getCachedStats()));

        return $count === 4
            ? ['default' => 1, 'sm' => 2, 'lg' => 4]
            : ['default' => 1, 'sm' => $count];
    }

    protected function getStats(): array
    {
        $user = Filament::auth()->user();
        $stats = [];

        if ($user->can('approve_user')) {
            $pendingUsers = User::query()->whereNull('approved_at')->count();
            $stats[] = Stat::make(__('Pending Users'), (string) $pendingUsers)
                ->description($pendingUsers ? __('See who is waiting') : __('Nobody is waiting'))
                ->descriptionIcon(Heroicon::ArrowRight)
                ->icon(Heroicon::UserGroup)
                ->color($pendingUsers ? 'warning' : 'gray')
                // the users list, filtered on the ones not approved yet (when they may open it)
                ->url(UserResource::canViewAny()
                    ? UserResource::getUrl('index', ['filters' => ['approved' => ['value' => '0']]])
                    : null);
        }

        if (ScheduleAccess::canApprove($user)) {
            $pendingSchedules = Schedule::query()->where('status', ScheduleStatus::Pending)->count();
            $stats[] = Stat::make(__('Pending Schedules'), (string) $pendingSchedules)
                ->description($pendingSchedules ? __('Open the approvals') : __('Nothing to decide'))
                ->descriptionIcon(Heroicon::ArrowRight)
                ->icon(Heroicon::CheckBadge)
                ->color($pendingSchedules ? 'warning' : 'gray')
                ->url(Approvals::getUrl());
        }

        if ($user->can('view_any_schedule')) {
            $week = AgendaSummary::weekTotals();
            $stats[] = Stat::make(__('This week'), trans_choice(':count schedule|:count schedules', $week['total']))
                ->description(__(':approved approved · :pending pending', ['approved' => $week['approved'], 'pending' => $week['pending']]))
                ->icon(Heroicon::CalendarDays)
                ->color($week['pending'] ? 'info' : 'success')
                ->url(ScheduleCalendar::getUrl());

            $today = AgendaSummary::days(CarbonImmutable::today(), 1)[0];
            $stats[] = Stat::make(__('Today'), trans_choice(':count person|:count people', $today['approved'] + $today['pending']))
                ->description(trans_choice(':count schedule|:count schedules', $today['schedules'])
                    . ($today['pending'] ? ' · ' . __(':count pending', ['count' => $today['pending']]) : ''))
                ->icon(Heroicon::Sun)
                ->color('primary')
                ->url(ScheduleCalendar::getUrl(['date' => CarbonImmutable::today()->toDateString()]));
        }

        return $stats;
    }
}
