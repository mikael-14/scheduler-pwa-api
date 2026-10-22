<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use App\Notifications\ScheduleActivity;
use App\Observers\FilamentNotificationObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** "All notifications": other people's schedules, by push / email (ScheduleActivityFeed). */
class AllNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/schedules';

    private function person(array $permissions, array $attributes = [], string $role = 'volunteer'): User
    {
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->syncPermissions($permissions);

        $user = User::factory()->create(array_merge(['approved_at' => now(), 'status' => true], $attributes));
        $user->assignRole($role);

        return $user;
    }

    private function volunteer(array $attributes = []): User
    {
        return $this->person([
            'view_any_schedule', 'view_schedule', 'create_schedule', 'update_schedule',
            'participate_schedule', 'cancel_participation_schedule',
        ], $attributes);
    }

    private function approver(): User
    {
        return $this->person(['view_any_schedule', 'view_schedule', 'approve_schedule'], [], 'approver');
    }

    private function book(User $owner, array $extra = []): int
    {
        $type = ScheduleType::firstOrCreate(['name' => 'Passeios'], ['color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');

        Sanctum::actingAs($owner);

        return $this->postJson(self::URL, array_merge([
            'schedule_type_id' => $type->id, 'start' => "$day 10:00", 'end' => "$day 12:00",
        ], $extra))->assertOk()->json('data.id');
    }

    private function eventsFor(User $user): array
    {
        return Notification::sent($user, ScheduleActivity::class)->map(fn($n) => $n->event)->all();
    }

    public function test_everyone_who_turned_it_on_hears_about_a_new_booking_but_not_the_people_involved(): void
    {
        Notification::fake();
        $watcher = $this->volunteer();
        $quiet = $this->volunteer(['all_push_notification' => false, 'all_email_notification' => false]);
        $inactive = $this->volunteer(['status' => false]);
        $approver = $this->approver();
        $owner = $this->volunteer();
        $friend = $this->volunteer();

        // booked together with a participant: one notice, not one per participant
        $this->book($owner, ['schedule_users' => [['user_id' => $friend->id]]]);

        $this->assertSame([ScheduleActivity::CREATED], $this->eventsFor($watcher));
        foreach ([$quiet, $inactive, $owner, $friend, $approver] as $notTold) {
            Notification::assertNotSentTo($notTold, ScheduleActivity::class);
        }
    }

    public function test_approvals_changes_cancellations_and_people_joining_are_announced(): void
    {
        Notification::fake();
        $watcher = $this->volunteer();
        $owner = $this->volunteer();
        $joiner = $this->volunteer();
        $id = $this->book($owner);

        Sanctum::actingAs($this->approver());
        $this->postJson(self::URL . "/$id/approve")->assertOk();

        Sanctum::actingAs($joiner);
        $this->postJson(self::URL . "/$id/participation")->assertOk();
        $this->deleteJson(self::URL . "/$id/participation")->assertOk();

        Sanctum::actingAs($owner);
        $day = now()->addDays(4)->format('Y-m-d');
        $this->patchJson(self::URL . "/$id", ['start' => "$day 10:00", 'end' => "$day 12:00"])->assertOk();
        Schedule::find($id)->update(['status' => ScheduleStatus::Cancelled]);

        $this->assertSame([
            ScheduleActivity::CREATED,
            ScheduleActivity::APPROVED,
            ScheduleActivity::JOINED,
            ScheduleActivity::LEFT,
            ScheduleActivity::CHANGED,
            ScheduleActivity::CANCELLED,
        ], $this->eventsFor($watcher));

        // the one who joined is not told about their own join / leave
        $this->assertNotContains(ScheduleActivity::JOINED, $this->eventsFor($joiner));
    }

    public function test_it_is_stored_as_history_and_email_and_push_follow_the_two_settings(): void
    {
        $emailOnly = $this->volunteer(['all_push_notification' => false]);
        $pushOnly = $this->volunteer(['all_email_notification' => false]);
        $this->book($this->volunteer());

        $stored = DatabaseNotification::query()->where('type', ScheduleActivity::class)->get()->keyBy('notifiable_id');
        $this->assertTrue($stored->has($emailOnly->id));
        $this->assertTrue($stored->has($pushOnly->id));

        // the PWA list shows them as agenda activity
        Sanctum::actingAs($pushOnly);
        $this->assertSame('activity', $this->getJson('/api/notifications')->json('data.0.kind'));

        $notification = new ScheduleActivity(Schedule::first(), ScheduleActivity::CREATED);
        $this->assertContains('mail', $notification->via($emailOnly));
        $this->assertNotContains('mail', $notification->via($pushOnly));

        // push: activity follows all_push_notification, personal ones push_notification
        $pushOnly->update(['push_notification' => false]);
        $this->assertTrue(FilamentNotificationObserver::wantsPush($pushOnly, $stored[$pushOnly->id]));
        $this->assertFalse(FilamentNotificationObserver::wantsPush($emailOnly, $stored[$emailOnly->id]));
        $personal = new DatabaseNotification(['data' => ['title' => 'x']]);
        $this->assertFalse(FilamentNotificationObserver::wantsPush($pushOnly, $personal));
    }

    public function test_the_settings_are_on_for_new_accounts_and_editable_from_the_app(): void
    {
        $user = $this->volunteer();
        $this->assertTrue($user->fresh()->all_push_notification);
        $this->assertTrue($user->fresh()->all_email_notification);

        Sanctum::actingAs($user);
        // the app reads all four settings
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email_notification', true)
            ->assertJsonPath('user.push_notification', true)
            ->assertJsonPath('user.all_email_notification', true)
            ->assertJsonPath('user.all_push_notification', true);

        $this->patchJson('/api/user', ['all_email_notification' => false])->assertOk();
        $this->assertFalse($user->fresh()->all_email_notification);

        $this->patchJson('/api/user', ['all_push_notification' => false])
            ->assertOk()
            ->assertJsonPath('user.all_push_notification', false)
            ->assertJsonPath('user.all_email_notification', false);
        $this->patchJson('/api/user', ['all_push_notification' => 'maybe'])->assertStatus(422);
    }

    public function test_permissions_are_listed_for_the_app_but_cannot_be_changed_through_the_profile(): void
    {
        $user = $this->volunteer();
        Sanctum::actingAs($user);

        $before = $this->getJson('/api/user')->assertOk()->json('permissions');
        $this->assertContains('view_any_schedule', $before);
        $this->assertNotContains('approve_schedule', $before);

        // unknown fields are ignored by the profile endpoint
        $this->patchJson('/api/user', ['permissions' => ['approve_schedule'], 'roles' => ['Super Admin'], 'status' => false])->assertOk();

        $this->assertFalse($user->fresh()->can('approve_schedule'));
        $this->assertTrue($user->fresh()->status);
        $this->assertSame($before, $this->getJson('/api/user')->json('permissions'));
    }
}
