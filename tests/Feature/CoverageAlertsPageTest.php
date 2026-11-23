<?php

namespace Tests\Feature;

use App\Filament\Pages\CoverageAlerts;
use App\Filament\Widgets\CoveragePreviewWidget;
use App\Filament\Widgets\SentCoverageAlertsWidget;
use App\Models\ScheduleType;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The admin panel's Coverage alerts page: a dry run of the check and the alerts sent, with its own permission. */
class CoverageAlertsPageTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleType $walks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-20 10:00', 'Europe/Lisbon')); // a Tuesday
        $this->walks = ScheduleType::create(['name' => 'Passeios', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => true]);
        config([
            'coverage.enabled' => true,
            'coverage.defaults.look_ahead_days' => 3,
            'coverage.defaults.urgent_days' => 1,
            'coverage.rules' => ['walks' => ['type' => 'Passeios']],
        ]);
        Filament::setCurrentPanel('admin');
        Notification::fake();
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'locale' => 'pt']);
        $role = Role::create(['name' => 'role' . $user->id, 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    public function test_shield_declares_the_page_permission_and_no_widget_ones(): void
    {
        $page = FilamentShield::getPages()[CoverageAlerts::class] ?? null;

        $this->assertNotNull($page, 'the page is missing from Shield → Roles → Pages');
        $this->assertSame([CoverageAlerts::PERMISSION], array_keys($page['permissions']));
        $this->assertArrayNotHasKey(CoveragePreviewWidget::class, FilamentShield::getWidgets() ?? []);
        $this->assertArrayNotHasKey(SentCoverageAlertsWidget::class, FilamentShield::getWidgets() ?? []);
    }

    public function test_only_who_has_the_permission_opens_the_page(): void
    {
        $this->actingAs($this->userWith(['view_any_schedule']))->get('/admin/coverage-alerts')->assertForbidden();
        $this->actingAs($this->userWith([CoverageAlerts::PERMISSION]))->get('/admin/coverage-alerts')->assertOk();
    }

    public function test_the_preview_is_a_dry_run_of_the_check(): void
    {
        $admin = $this->userWith([CoverageAlerts::PERMISSION]);
        // the urgent summary for tomorrow already went out this morning
        DB::table('coverage_alerts')->insert([
            'rule' => 'walks', 'schedule_type_id' => $this->walks->id, 'date' => '2026-10-21', 'days_before' => 1,
            'level' => 'urgent', 'sent_on' => '2026-10-20', 'approved_count' => 0, 'pending_count' => 0, 'recipients' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)->test(CoveragePreviewWidget::class)
            ->assertSee('Passeios: todos os dias')
            ->assertSee('quarta-feira, 21 de outubro')
            ->assertSee('Já enviado')
            ->assertSee('Seria enviado')
            // as if it were another day
            ->set('tableFilters.day.day', '2026-11-02')
            ->assertSee('terça-feira, 3 de novembro')
            ->assertDontSee('quarta-feira, 21 de outubro');

        // nothing sent, nothing recorded
        Notification::assertNothingSent();
        $this->assertSame(1, DB::table('coverage_alerts')->count());
    }

    public function test_the_alerts_sent_are_listed(): void
    {
        $admin = $this->userWith([CoverageAlerts::PERMISSION]);
        $this->artisan('coverage:check')->assertSuccessful();

        Livewire::actingAs($admin)->test(SentCoverageAlertsWidget::class)
            ->assertCanSeeTableRecords(\App\Models\SentCoverageAlert::all())
            ->assertSee('Urgente')
            ->assertSee('Próximos dias');
    }
}
