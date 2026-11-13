<?php

namespace App\Filament\Resources\QueueMonitors\Pages;

use App\Filament\Resources\QueueMonitors\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListFailures as BaseListFailures;

/** The failed jobs, grouped: only for who may see the monitor (the plugin checks no permission). */
class ListFailures extends BaseListFailures
{
    protected static string $resource = QueueMonitorResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return parent::canAccess($parameters) && QueueMonitorResource::canViewAny();
    }
}
