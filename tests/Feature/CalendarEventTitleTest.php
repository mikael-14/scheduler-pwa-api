<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Livewire\CalendarWidget;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\ScheduleUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The admin calendar's event titles: owner, "+N" others, and real emoji (never garbled bytes). */
class CalendarEventTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_title_counts_the_participants_and_uses_real_symbols(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $owner = User::factory()->create(['approved_at' => now(), 'status' => true, 'name' => 'Maria']);
        $type = ScheduleType::create(['name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $start = now()->addDays(2)->setTime(10, 0);
        $schedule = Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => ScheduleStatus::Approved,
            'start' => $start, 'end' => $start->copy()->addHour(), 'description' => 'Bring keys',
        ]));
        foreach (User::factory(2)->create(['approved_at' => now(), 'status' => true]) as $participant) {
            ScheduleUser::withoutEvents(fn() => ScheduleUser::create(['schedule_id' => $schedule->id, 'user_id' => $participant->id]));
        }

        $viewer = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view_any_schedule', 'guard_name' => 'web']));
        $viewer->assignRole($role);
        $this->actingAs($viewer);

        $events = Livewire::test(CalendarWidget::class)->instance()->fetchEvents([
            'start' => now()->startOfDay()->toIso8601String(), 'end' => now()->addWeek()->toIso8601String(),
        ]);

        $this->assertSame("Maria +2 \u{1F4AC} \u{00B7} Cleaning", $events[0]['title']);
        // the type's colour for the dot before the title (the background is the status)
        $this->assertSame('#112233', $events[0]['typeColor']);
        $this->assertTrue(mb_check_encoding($events[0]['title'], 'UTF-8'));
        // no double-encoded UTF-8 ("Ã", "â€", "ðŸ"...) left in the widget
        $this->assertDoesNotMatchRegularExpression('/Ã|â€|âŒ|ðŸ|Â/u', file_get_contents(app_path('Livewire/CalendarWidget.php')));

        // the schedules table: a note icon only where there is a description
        $plain = Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => ScheduleStatus::Approved,
            'start' => $start->copy()->addDay(), 'end' => $start->copy()->addDay()->addHour(),
        ]));
        Livewire::test(\App\Filament\Resources\Schedules\Pages\ListSchedules::class)
            ->assertTableColumnStateSet('has_description', true, $schedule)
            ->assertTableColumnStateSet('has_description', false, $plain);
    }
}
