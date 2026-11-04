<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Filament\Widgets\TodaySchedulesWidget;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Rupadana\ApiService\Models\Token;
use Rupadana\ApiService\Resources\TokenResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The screens outside the main resources: API tokens and the dashboard's Today list. */
class OtherScreensAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions, ?string $roleName = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName ?? 'r' . uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_tokens_are_only_reached_by_their_owner_or_a_super_admin(): void
    {
        $tokens = ['view_any_token', 'view_token', 'create_token', 'update_token', 'delete_token'];
        $admin = $this->userWith($tokens);
        $super = $this->userWith($tokens, 'super_admin');
        $theirs = Token::findOrFail($super->createToken('phone')->accessToken->id);
        $mine = Token::findOrFail($admin->createToken('laptop')->accessToken->id);

        $this->actingAs($admin);
        $this->assertFalse($admin->can('update', $theirs));
        $this->assertFalse($admin->can('delete', $theirs));
        $this->assertTrue($admin->can('delete', $mine));
        // the list shows only your own
        Livewire::test(\Rupadana\ApiService\Resources\TokenResource\Pages\ListTokens::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->assertTrue($super->can('delete', $mine));

        // the app's policy is the one in use, and bulk delete follows delete_token
        $this->assertInstanceOf(\App\Policies\TokenPolicy::class, \Illuminate\Support\Facades\Gate::getPolicyFor($mine));
        $this->assertTrue($admin->can('deleteAny', Token::class));
        Livewire::test(\Rupadana\ApiService\Resources\TokenResource\Pages\ListTokens::class)
            ->selectTableRecords([$mine->getKey()])
            ->callAction(\Filament\Actions\Testing\TestAction::make('delete')->table()->bulk());
        $this->assertNull(Token::find($mine->getKey()));
        $this->assertNotNull(Token::find($theirs->getKey()));
    }

    public function test_today_shows_only_what_the_person_may_see(): void
    {
        $member = $this->userWith(['view_any_schedule']);
        $type = ScheduleType::create(['name' => 'Walks', 'color' => '#123456', 'status' => true, 'range' => true, 'all_day' => false]);
        $make = fn(ScheduleStatus $status, User $owner) => Schedule::withoutEvents(fn() => Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => $status,
            'start' => now()->startOfDay()->addHours(10), 'end' => now()->startOfDay()->addHours(11),
        ]));
        $other = User::factory()->create();
        $approved = $make(ScheduleStatus::Approved, $other);
        $notShown = $make(ScheduleStatus::NotShown, $other);
        $ownNotShown = $make(ScheduleStatus::NotShown, $member);

        $this->actingAs($member);
        Livewire::test(TodaySchedulesWidget::class)
            ->assertCanSeeTableRecords([$approved, $ownNotShown])
            ->assertCanNotSeeTableRecords([$notShown]);

        // with view_inactive_schedule: everything
        $this->actingAs($this->userWith(['view_any_schedule', 'view_inactive_schedule']));
        Livewire::test(TodaySchedulesWidget::class)->assertCanSeeTableRecords([$approved, $notShown, $ownNotShown]);
    }
}
