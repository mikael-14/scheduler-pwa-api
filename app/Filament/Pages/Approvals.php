<?php

namespace App\Filament\Pages;

use App\Enums\ScheduleStatus;
use App\Filament\Widgets\PendingSchedulesWidget;
use App\Models\Schedule;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The approver's inbox: only the pending schedules, with approve/reject on each row.
 * Same audience as the widget it hosts (approve_schedule or update_status_schedule).
 */
class Approvals extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::CheckBadge;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'approvals';

    public static function getNavigationGroup(): ?string
    {
        return __('Scheduler');
    }

    public static function getNavigationLabel(): string
    {
        return __('Approvals');
    }

    public function getTitle(): string
    {
        return __('Approvals');
    }

    public static function canAccess(): bool
    {
        return PendingSchedulesWidget::canView();
    }

    /** How many schedules are waiting, so approvers see it without opening the page. */
    public static function getNavigationBadge(): ?string
    {
        $count = Schedule::query()->where('status', ScheduleStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PendingSchedulesWidget::make(),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
