<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/schedules';

    private function role(string $name, array $permissions): Role
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role->syncPermissions($permissions);

        return $role;
    }

    private function member(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'approved_at' => now(),
            'status' => true,
        ], $attributes));
        $user->assignRole($this->role('member', [
            'view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule', 'delete_schedule',
            'view_any_schedule_type',
        ]));

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->assignRole($this->role('approver', [
            'view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule', 'delete_schedule',
            'approve_schedule', 'update_any_schedule', 'delete_any_schedule', 'create_any_schedule',
        ]));

        return $user;
    }

    private function type(array $attributes = []): ScheduleType
    {
        return ScheduleType::create(array_merge([
            'name' => 'Cleaning',
            'color' => '#112233',
            'status' => true,
            'range' => true,
            'all_day' => false,
            'min_time' => '08:00',
            'max_time' => '18:00',
        ], $attributes));
    }

    private function payload(ScheduleType $type, array $overrides = []): array
    {
        $day = now()->addDays(3)->format('Y-m-d');

        return array_merge([
            'schedule_type_id' => $type->id,
            'start' => "$day 10:00",
            'end' => "$day 12:00",
        ], $overrides);
    }

    public function test_pending_account_cannot_use_the_api_but_can_read_itself(): void
    {
        $user = $this->member(['approved_at' => null]);
        Sanctum::actingAs($user);

        $this->getJson(self::URL)->assertStatus(403)->assertJsonPath('code', 'account_pending');
        $this->getJson('/api/user')->assertOk()->assertJsonPath('user.approved_at', null);
    }

    public function test_inactive_account_is_blocked_everywhere(): void
    {
        $user = $this->member(['status' => false]);
        Sanctum::actingAs($user);

        $this->getJson('/api/user')->assertStatus(403)->assertJsonPath('code', 'account_inactive');
        $this->getJson(self::URL)->assertStatus(403);
    }

    public function test_login_is_refused_for_inactive_accounts_and_throttled(): void
    {
        User::factory()->create(['email' => 'a@x.test', 'password' => 'secret-pass', 'status' => false]);

        $this->postJson('/api/auth/login', ['email' => 'a@x.test', 'password' => 'secret-pass'])
            ->assertStatus(403)->assertJsonPath('code', 'account_inactive');

        for ($i = 0; $i < 12; $i++) {
            $last = $this->postJson('/api/auth/login', ['email' => 'a@x.test', 'password' => 'wrong']);
        }
        $last->assertStatus(429);
    }

    public function test_deactivating_a_user_revokes_their_tokens(): void
    {
        $user = $this->member();
        $user->createToken('pwa');
        $this->assertSame(1, $user->tokens()->count());

        $user->update(['status' => false]);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_create_forces_owner_and_pending_status(): void
    {
        $member = $this->member();
        $other = $this->member();
        $type = $this->type();
        Sanctum::actingAs($member);

        $response = $this->postJson(self::URL, $this->payload($type, [
            'user_id' => $other->id,
            'status' => 'approved',
            'internal_note' => 'sneaky',
        ]));

        // Requesting a status the caller may not set is refused rather than silently downgraded
        $response->assertStatus(422)->assertJsonValidationErrors('status');

        $ok = $this->postJson(self::URL, $this->payload($type, ['user_id' => $other->id]))->assertOk();
        $this->assertSame($member->id, $ok->json('data.user_id'));
        $this->assertSame('pending', $ok->json('data.status'));
        $this->assertArrayNotHasKey('internal_note', $ok->json('data'));
        $this->assertNull(Schedule::find($ok->json('data.id'))->internal_note);
    }

    public function test_type_rules_are_enforced_on_the_api(): void
    {
        Sanctum::actingAs($this->member());
        $type = $this->type();
        $day = now()->addDays(3)->format('Y-m-d');

        // before min_time
        $this->postJson(self::URL, $this->payload($type, ['start' => "$day 06:00", 'end' => "$day 09:00"]))
            ->assertStatus(422)->assertJsonValidationErrors('start');

        // after max_time
        $this->postJson(self::URL, $this->payload($type, ['start' => "$day 16:00", 'end' => "$day 20:00"]))
            ->assertStatus(422)->assertJsonValidationErrors('end');

        // range type needs an end
        $this->postJson(self::URL, ['schedule_type_id' => $type->id, 'start' => "$day 10:00"])
            ->assertStatus(422)->assertJsonValidationErrors('end');

        // all-day not allowed by the type
        $this->postJson(self::URL, $this->payload($type, ['all_day' => true]))
            ->assertStatus(422)->assertJsonValidationErrors('all_day');

        // inactive type
        $inactive = $this->type(['name' => 'Old', 'status' => false]);
        $this->postJson(self::URL, $this->payload($inactive))
            ->assertStatus(422)->assertJsonValidationErrors('schedule_type_id');

        // past date
        $past = now()->subDays(2)->format('Y-m-d');
        $this->postJson(self::URL, $this->payload($type, ['start' => "$past 10:00", 'end' => "$past 11:00"]))
            ->assertStatus(422)->assertJsonValidationErrors('start');

        // a valid one still works
        $this->postJson(self::URL, $this->payload($type))->assertOk();
    }

    public function test_only_owner_or_any_permission_can_change_or_delete(): void
    {
        $owner = $this->member();
        $stranger = $this->member();
        $type = $this->type();
        $schedule = Schedule::create(array_merge($this->payload($type), ['user_id' => $owner->id, 'status' => ScheduleStatus::Pending]));

        Sanctum::actingAs($stranger);
        $this->patchJson(self::URL . "/{$schedule->id}", ['description' => 'hijack'])->assertStatus(403);
        $this->deleteJson(self::URL . "/{$schedule->id}")->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->patchJson(self::URL . "/{$schedule->id}", ['description' => 'mine'])->assertOk();
        $this->assertSame('mine', $schedule->fresh()->description);

        Sanctum::actingAs($this->admin());
        $this->patchJson(self::URL . "/{$schedule->id}", ['status' => 'approved'])->assertOk();
        $this->assertSame(ScheduleStatus::Approved, $schedule->fresh()->status);
    }

    public function test_owner_cannot_self_approve_and_slot_change_requires_reapproval(): void
    {
        $owner = $this->member();
        $type = $this->type();
        $schedule = Schedule::create(array_merge($this->payload($type), ['user_id' => $owner->id, 'status' => ScheduleStatus::Approved]));
        $day = now()->addDays(4)->format('Y-m-d');

        Sanctum::actingAs($owner);
        $this->patchJson(self::URL . "/{$schedule->id}", ['status' => 'completed'])->assertStatus(422);

        $this->patchJson(self::URL . "/{$schedule->id}", ['start' => "$day 09:00", 'end' => "$day 10:00"])->assertOk();
        $this->assertSame(ScheduleStatus::Pending, $schedule->fresh()->status);

        // approved schedules are cancelled, not deleted, by their owner
        $schedule->fresh()->update(['status' => ScheduleStatus::Approved]);
        $this->deleteJson(self::URL . "/{$schedule->id}")->assertStatus(422)->assertJsonPath('code', 'cancel_instead');
    }

    private function approver(): User
    {
        // Only the approval permission (plus read access): cannot create or edit anything
        $user = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $user->assignRole($this->role('approver-only', ['view_any_schedule', 'view_schedule', 'approve_schedule']));

        return $user;
    }

    public function test_approve_only_permission_can_approve_and_reject_but_not_edit(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $type = $this->type();
        $schedule = Schedule::create(array_merge($this->payload($type), ['user_id' => $owner->id, 'status' => ScheduleStatus::Pending]));
        $schedule->schedule_users()->create(['user_id' => $other->id, 'status' => ScheduleStatus::Pending]);

        Sanctum::actingAs($this->approver());

        // cannot edit content, delete, create, or put arbitrary statuses
        $this->patchJson(self::URL . "/{$schedule->id}", ['description' => 'x'])->assertStatus(403);
        $this->patchJson(self::URL . "/{$schedule->id}", ['status' => 'completed'])->assertStatus(403);
        $this->deleteJson(self::URL . "/{$schedule->id}")->assertStatus(403);
        $this->postJson(self::URL, $this->payload($type))->assertStatus(403);

        // can approve: the pending participant follows
        $this->postJson(self::URL . "/{$schedule->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame(ScheduleStatus::Approved, $schedule->fresh()->status);
        $this->assertSame(ScheduleStatus::Approved, $schedule->schedule_users()->first()->status);

        // an already decided schedule is not re-decided with this permission alone
        $this->postJson(self::URL . "/{$schedule->id}/reject")->assertStatus(422)->assertJsonPath('code', 'not_pending');

        $second = Schedule::create(array_merge($this->payload($type), ['user_id' => $owner->id, 'status' => ScheduleStatus::Pending]));
        $this->postJson(self::URL . "/{$second->id}/reject")->assertOk();
        $this->assertSame(ScheduleStatus::Rejected, $second->fresh()->status);
    }

    public function test_pending_list_is_the_approvers_work_list_and_is_closed_to_members(): void
    {
        $owner = $this->member();
        $type = $this->type();
        $later = Schedule::create(array_merge($this->payload($type, ['start' => now()->addDays(5)->format('Y-m-d') . ' 10:00', 'end' => now()->addDays(5)->format('Y-m-d') . ' 12:00']), ['user_id' => $owner->id, 'status' => ScheduleStatus::Pending]));
        $sooner = Schedule::create(array_merge($this->payload($type, ['start' => now()->addDays(2)->format('Y-m-d') . ' 10:00', 'end' => now()->addDays(2)->format('Y-m-d') . ' 12:00']), ['user_id' => $owner->id, 'status' => ScheduleStatus::Pending]));
        Schedule::create(array_merge($this->payload($type), ['user_id' => $owner->id, 'status' => ScheduleStatus::Approved]));

        Sanctum::actingAs($owner);
        $this->getJson(self::URL . '/pending')->assertStatus(403);

        // approve-only role: no create/edit rights, and no email or other user data in the payload
        $approver = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $approver->assignRole($this->role('approve-no-read', ['approve_schedule']));
        Sanctum::actingAs($approver);

        $response = $this->getJson(self::URL . '/pending')->assertOk();
        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $later->id)
            ->assertJsonPath('data.0.user.name', $owner->name)
            ->assertJsonPath('data.0.schedule_type.id', $type->id);
        $this->assertArrayNotHasKey('email', $response->json('data.0.user'));
    }

    public function test_regular_members_cannot_approve(): void
    {
        $owner = $this->member();
        $type = $this->type();
        $schedule = Schedule::create(array_merge($this->payload($type), ['user_id' => $owner->id, 'status' => ScheduleStatus::Pending]));

        Sanctum::actingAs($owner);
        $this->postJson(self::URL . "/{$schedule->id}/approve")->assertStatus(403);
        $this->assertSame(ScheduleStatus::Pending, $schedule->fresh()->status);
    }

    public function test_update_status_permission_sets_any_status_on_any_schedule_but_cannot_edit_content(): void
    {
        $owner = $this->member();
        $type = $this->type();
        $schedule = Schedule::create(array_merge($this->payload($type), ['user_id' => $owner->id, 'status' => ScheduleStatus::Approved]));

        $manager = User::factory()->create(['approved_at' => now(), 'status' => true]);
        $manager->assignRole($this->role('status-manager', ['view_any_schedule', 'view_schedule', 'update_status_schedule']));
        Sanctum::actingAs($manager);

        $this->patchJson(self::URL . "/{$schedule->id}", ['status' => 'completed'])->assertOk();
        $this->assertSame(ScheduleStatus::Completed, $schedule->fresh()->status);

        $this->patchJson(self::URL . "/{$schedule->id}", ['status' => 'not_shown', 'description' => 'x'])->assertStatus(403);
    }

    public function test_internal_note_is_hidden_from_regular_users_and_visible_to_staff(): void
    {
        $owner = $this->member();
        $type = $this->type();
        Schedule::create(array_merge($this->payload($type), [
            'user_id' => $owner->id, 'status' => ScheduleStatus::Pending, 'internal_note' => 'staff only',
        ]));

        Sanctum::actingAs($this->member());
        $this->assertArrayNotHasKey('internal_note', $this->getJson(self::URL)->assertOk()->json('data.0'));

        Sanctum::actingAs($this->admin());
        $this->assertSame('staff only', $this->getJson(self::URL)->assertOk()->json('data.0.internal_note'));
    }

    public function test_list_filters_and_page_size_cap(): void
    {
        $a = $this->member();
        $b = $this->member();
        $type = $this->type();
        Schedule::create(array_merge($this->payload($type), ['user_id' => $a->id, 'status' => ScheduleStatus::Pending]));
        Schedule::create(array_merge($this->payload($type), ['user_id' => $b->id, 'status' => ScheduleStatus::Approved]));

        Sanctum::actingAs($a);
        $this->assertCount(2, $this->getJson(self::URL)->json('data'));
        $this->assertCount(1, $this->getJson(self::URL . '?filter[mine]=1')->json('data'));
        $this->assertCount(1, $this->getJson(self::URL . '?filter[status]=approved')->json('data'));
        $this->assertCount(1, $this->getJson(self::URL . '?filter[user_id]=' . $b->id)->json('data'));
        $this->assertSame(500, $this->getJson(self::URL . '?per_page=10000')->json('meta.per_page'));
    }

    public function test_patch_profile_actually_updates_and_password_needs_current(): void
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'password' => 'old-password']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/user', ['name' => 'New Name'])->assertOk();
        $this->assertSame('New Name', $user->fresh()->name);

        $this->patchJson('/api/user', ['password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->patchJson('/api/user', [
            'current_password' => 'old-password', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1',
        ])->assertOk();
    }
}
