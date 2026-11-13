<?php

namespace App\Filament\Resources\QueueMonitors;

use App\Filament\Resources\QueueMonitors\Pages\ListFailures;
use App\Filament\Resources\QueueMonitors\Pages\ListPendingJobs;
use Croustibat\FilamentJobsMonitor\Models\QueueJob;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource as BaseQueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListQueueMonitors;

/**
 * The plugin's jobs monitor, in Administração and behind QueueMonitorPolicy. Its Pending and
 * Failures pages check no permission of their own (only whether the feature is on), so they are
 * replaced here by ones that also need the monitor's view_any (see Pages).
 */
class QueueMonitorResource extends BaseQueueMonitorResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListQueueMonitors::route('/'),
        ];

        if (resolve(QueueJob::class)::isSupported()) {
            $pages['pending'] = ListPendingJobs::route('/pending');
        }

        if (config('filament-jobs-monitor.failures.enabled', true)) {
            $pages['failures'] = ListFailures::route('/failures');
        }

        return $pages;
    }
}
