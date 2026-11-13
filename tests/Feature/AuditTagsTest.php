<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Audit entries: tagged with where they came from and what changed (AuditTags), never empty,
 * and saving a user leaves approved_at alone unless the Approved toggle changed.
 */
class AuditTagsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Laravel Auditing skips console runs (tests count as console)
        config(['audit.console' => true]);
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        Filament::setCurrentPanel('admin');

        $role = Role::create(['name' => 'manager', 'guard_name' => 'web']);
        foreach (['view_any_user', 'view_user', 'update_user', 'update_any_user', 'approve_user'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $this->admin = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $this->admin->assignRole($role);
    }

    private function lastAudit(User|Schedule $model): Audit
    {
        return Audit::where('auditable_type', $model::class)->where('auditable_id', $model->id)->latest('id')->firstOrFail();
    }

    private function tags(Audit $audit): array
    {
        return explode(',', (string) $audit->tags);
    }

    public function test_saving_a_user_only_touches_approved_at_when_the_toggle_changes(): void
    {
        $member = User::factory()->create(['approved_at' => '2026-10-01 10:00:00', 'status' => true]);
        $this->actingAs($this->admin, 'web');

        // another field: approved_at stays, and is not in the entry
        Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();
        $audit = $this->lastAudit($member);
        $this->assertSame(['name'], array_keys($audit->new_values));
        $this->assertSame('2026-10-01 10:00:00', $member->fresh()->getRawOriginal('approved_at'));
        $this->assertSame(['admin', 'user', 'profile'], $this->tags($audit));

        // turned off, then on again: cleared, then a new date
        Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
            ->fillForm(['approved' => false])
            ->call('save');
        $this->assertNull($member->fresh()->approved_at);
        $this->assertContains('unapproved', $this->tags($this->lastAudit($member)));

        Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
            ->fillForm(['approved' => true])
            ->call('save');
        $this->assertTrue($member->fresh()->approved_at->isToday());
        $this->assertContains('approved', $this->tags($this->lastAudit($member)));
    }

    public function test_no_empty_entries_and_a_password_change_says_only_that(): void
    {
        $member = User::factory()->create(['approved_at' => now(), 'status' => true, 'password' => Hash::make('secret-123')]);
        $before = Audit::count();

        // signing in with "remember me" and out changes only remember_token (never audited)
        Auth::guard('web')->login($member, true);
        Auth::guard('web')->logout();
        $member->fresh()->touch();
        $this->assertSame($before, Audit::count());
        $this->assertSame(0, Audit::get()->filter(fn(Audit $a) => empty($a->old_values) && empty($a->new_values))->count());

        // the password, from the PWA
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($member->fresh());
        $this->patchJson('/api/user', [
            'current_password' => 'secret-123', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1',
        ])->assertOk();

        $audit = $this->lastAudit($member);
        $this->assertSame(['password_changed' => true], $audit->new_values);
        $this->assertSame(['password_changed' => false], $audit->old_values);
        $this->assertSame(['pwa', 'user', 'password', 'self'], $this->tags($audit));
    }

    public function test_the_audit_tables_show_the_tags_and_search_them(): void
    {
        $type = ScheduleType::create(['name' => 'Passeios', 'status' => true, 'range' => true]);
        $type->update(['status' => false]);
        $member = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $member->update(['locale' => 'en']);
        [$typeAudit, $userAudit] = [
            Audit::where('auditable_type', ScheduleType::class)->latest('id')->first(),
            Audit::where('auditable_type', User::class)->where('auditable_id', $member->id)->latest('id')->first(),
        ];

        $auditor = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
        foreach (['view_any_audit', 'view_audit', 'view_any_schedule_type', 'view_schedule_type'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $auditor->assignRole($role);
        $this->actingAs($auditor, 'web');

        Livewire::test(\App\Filament\Resources\Audits\Pages\ListAudits::class)
            ->assertTableColumnExists('tags')
            ->assertTableColumnStateSet('tags', ['admin', 'schedule-type', 'Passeios', 'activation'], $typeAudit)
            ->searchTable('activation')
            ->assertCanSeeTableRecords([$typeAudit])
            ->assertCanNotSeeTableRecords([$userAudit]);

        Livewire::test(\App\Filament\Resources\ScheduleTypes\RelationManagers\AuditsRelationManager::class, [
            'ownerRecord' => $type, 'pageClass' => \App\Filament\Resources\ScheduleTypes\Pages\ViewScheduleType::class,
        ])
            ->assertTableColumnExists('tags')
            ->assertSee('activation');
    }

    public function test_schedules_are_tagged_with_their_type_status_and_change(): void
    {
        $type = ScheduleType::create(['name' => 'Passeios', 'status' => true, 'range' => true]);
        $day = now()->addDays(3)->format('Y-m-d');
        $schedule = Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $this->admin->id, 'status' => ScheduleStatus::Pending,
            'start' => "$day 10:00", 'end' => "$day 12:00",
        ]));
        $schedule->update(['status' => ScheduleStatus::Approved]);

        $this->assertSame(['admin', 'schedule', 'Passeios', 'approved', 'status-change'], $this->tags($this->lastAudit($schedule)));

        $type->update(['name' => 'Passeios, manhã']);
        $typeAudit = Audit::where('auditable_type', ScheduleType::class)->latest('id')->first();
        // the comma inside the name does not split it
        $this->assertSame(['admin', 'schedule-type', 'Passeios  manhã'], $this->tags($typeAudit));
    }
}
