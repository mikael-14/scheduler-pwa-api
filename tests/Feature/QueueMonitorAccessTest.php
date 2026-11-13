<?php

namespace Tests\Feature;

use App\Filament\Resources\QueueMonitors\Pages\ListFailures;
use App\Filament\Resources\QueueMonitors\Pages\ListPendingJobs;
use App\Filament\Resources\QueueMonitors\QueueMonitorResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule as Scheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The jobs monitor (croustibat/filament-jobs-monitor): every page, including the plugin's
 * Pending and Failures (which check no permission themselves), needs view_any_queue_monitor.
 */
class QueueMonitorAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $role = Role::create(['name' => 'role-' . $user->id, 'guard_name' => 'web']);
        // some admin-panel access, so only the monitor's own permission is tested
        foreach (['view_any_schedule', ...$permissions] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    /** Pending only exists with the database queue (tests run on sync): checked through canAccess. */
    private function urls(): array
    {
        return [
            QueueMonitorResource::getUrl('index'),
            QueueMonitorResource::getUrl('failures'),
        ];
    }

    public function test_every_page_needs_the_monitor_permission(): void
    {
        $without = $this->userWith([]);
        $with = $this->userWith(['view_any_queue_monitor']);

        $this->actingAs($without);
        foreach ($this->urls() as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->actingAs($with);
        foreach ($this->urls() as $url) {
            $this->get($url)->assertOk();
        }

        // the pages' own checks, with the database queue (as in production) so Pending exists
        config(['queue.default' => 'database']);
        $this->actingAs($without);
        $this->assertFalse(QueueMonitorResource::canViewAny());
        $this->assertFalse(ListPendingJobs::canAccess());
        $this->assertFalse(ListFailures::canAccess());
        // the plugin's own pages would let them in: why the app replaces them
        $this->assertTrue(\Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListPendingJobs::canAccess());

        $this->actingAs($with);
        $this->assertTrue(ListPendingJobs::canAccess());
        $this->assertTrue(ListFailures::canAccess());
        // the resource serves the app's pages, not the plugin's
        $this->assertSame(ListPendingJobs::class, QueueMonitorResource::getPages()['pending']->getPage());
        $this->assertSame(ListFailures::class, QueueMonitorResource::getPages()['failures']->getPage());
    }

    public function test_the_records_are_pruned_daily(): void
    {
        $event = collect(app(Scheduler::class)->events())
            ->first(fn($event) => str_contains($event->command, 'filament-jobs-monitor:prune'));

        $this->assertNotNull($event, 'the jobs monitor is not pruned');
        $this->assertSame('30 3 * * *', $event->expression);
    }
}
