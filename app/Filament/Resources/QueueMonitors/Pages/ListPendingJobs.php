<?php

namespace App\Filament\Resources\QueueMonitors\Pages;

use App\Filament\Resources\QueueMonitors\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListPendingJobs as BaseListPendingJobs;

/** The jobs waiting in the queue: only for who may see the monitor (the plugin checks no permission). */
class ListPendingJobs extends BaseListPendingJobs
{
    protected static string $resource = QueueMonitorResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return parent::canAccess($parameters) && QueueMonitorResource::canViewAny();
    }
}
