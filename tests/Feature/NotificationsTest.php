<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use App\Notifications\PwaWebPushNotification;
use App\Notifications\SchedulePendingApproval;
use App\Notifications\ScheduleStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['approved_at' => now(), 'status' => true], $attributes));
    }

    private function approver(): User
    {
        $user = $this->user(['locale' => 'pt']);
        $role = Role::firstOrCreate(['name' => 'approver', 'guard_name' => 'web']);
        foreach (['view_any_schedule', 'view_schedule', 'approve_schedule'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role->syncPermissions(['view_any_schedule', 'view_schedule', 'approve_schedule']);
        $user->assignRole($role);

        return $user;
    }

    private function pendingSchedule(User $owner): Schedule
    {
        $type = ScheduleType::create(['name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');

        return Schedule::create([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => ScheduleStatus::Pending,
            'start' => "$day 10:00", 'end' => "$day 12:00",
        ]);
    }

    private function userWithPermissions(array $permissions, array $attributes = []): User
    {
        static $n = 0;
        $n++;

        $user = $this->user($attributes);
        $role = Role::firstOrCreate(['name' => "role-$n", 'guard_name' => 'web']);
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user;
    }

    public function test_approvers_are_told_when_a_new_schedule_is_waiting(): void
    {
        $approver = $this->userWithPermissions(['approve_schedule']);
        $statusManager = $this->userWithPermissions(['update_status_schedule']);
        $inactive = $this->userWithPermissions(['approve_schedule'], ['status' => false]);
        $unapproved = $this->userWithPermissions(['approve_schedule'], ['approved_at' => null]);
        $regular = $this->userWithPermissions(['view_any_schedule']);

        $member = $this->userWithPermissions(['view_any_schedule', 'create_schedule']);
        $type = ScheduleType::create(['name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');

        Notification::fake();
        Sanctum::actingAs($member);
        $this->postJson('/api/admin/schedules', [
            'schedule_type_id' => $type->id, 'start' => "$day 10:00", 'end' => "$day 12:00",
        ])->assertOk();

        Notification::assertSentTo($approver, SchedulePendingApproval::class);
        Notification::assertSentTo($statusManager, SchedulePendingApproval::class);
        Notification::assertNotSentTo($inactive, SchedulePendingApproval::class);
        Notification::assertNotSentTo($unapproved, SchedulePendingApproval::class);
        Notification::assertNotSentTo($regular, SchedulePendingApproval::class);
        Notification::assertNotSentTo($member, SchedulePendingApproval::class);
    }

    public function test_an_approver_creating_a_schedule_is_not_told_about_their_own_request(): void
    {
        $approver = $this->userWithPermissions(['approve_schedule', 'create_schedule', 'view_any_schedule']);
        $other = $this->userWithPermissions(['approve_schedule']);
        $type = ScheduleType::create(['name' => 'Cleaning', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');

        Notification::fake();
        Sanctum::actingAs($approver);
        $this->postJson('/api/admin/schedules', [
            'schedule_type_id' => $type->id, 'start' => "$day 10:00", 'end' => "$day 12:00",
        ])->assertOk();

        Notification::assertNotSentTo($approver, SchedulePendingApproval::class);
        Notification::assertSentTo($other, SchedulePendingApproval::class);
    }

    public function test_moving_an_approved_schedule_asks_the_approvers_again(): void
    {
        $approver = $this->userWithPermissions(['approve_schedule']);
        $owner = $this->userWithPermissions(['view_any_schedule', 'update_schedule']);
        $schedule = $this->pendingSchedule($owner);
        $schedule->update(['status' => ScheduleStatus::Approved]);
        $day = now()->addDays(4)->format('Y-m-d');

        Notification::fake();
        Sanctum::actingAs($owner);
        $this->patchJson("/api/admin/schedules/{$schedule->id}", ['start' => "$day 09:00", 'end' => "$day 10:00"])->assertOk();

        $this->assertSame(ScheduleStatus::Pending, $schedule->fresh()->status);
        Notification::assertSentTo($approver, SchedulePendingApproval::class);
    }

    public function test_pending_notification_points_to_the_admin_panel_and_is_in_the_approvers_language(): void
    {
        $approver = $this->userWithPermissions(['approve_schedule'], ['locale' => 'en', 'email_notification' => true]);
        $owner = $this->user(['name' => 'Maria']);

        $this->pendingSchedule($owner);

        $stored = $approver->notifications()->first();
        $this->assertNotNull($stored);
        $this->assertSame('New schedule awaiting approval', $stored->data['title']);
        $this->assertStringContainsString('Maria', $stored->data['body']);
        $this->assertSame('warning', $stored->data['status']);
        $this->assertStringContainsString('/admin/schedules/', $stored->data['actions'][0]['url']);

        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('/admin/schedules/', $messages->first()->getOriginalMessage()->getHtmlBody());
    }

    public function test_owner_is_notified_in_app_only_when_email_is_off(): void
    {
        Notification::fake();
        $owner = $this->user(['email_notification' => false]);
        $schedule = $this->pendingSchedule($owner);

        Sanctum::actingAs($this->approver());
        $this->postJson("/api/admin/schedules/{$schedule->id}/approve")->assertOk();

        Notification::assertSentTo($owner, ScheduleStatusChanged::class, function ($notification, $channels) {
            return $channels === ['database'];
        });
    }

    public function test_email_channel_is_added_when_the_user_enabled_it(): void
    {
        Notification::fake();
        $owner = $this->user(['email_notification' => true]);
        $schedule = $this->pendingSchedule($owner);

        Sanctum::actingAs($this->approver());
        $this->postJson("/api/admin/schedules/{$schedule->id}/reject")->assertOk();

        Notification::assertSentTo($owner, ScheduleStatusChanged::class, function ($notification, $channels) {
            return $channels === ['database', 'mail'];
        });
    }

    public function test_participants_are_notified_and_the_actor_is_not(): void
    {
        Notification::fake();
        $owner = $this->user();
        $participant = $this->user();
        $schedule = $this->pendingSchedule($owner);
        $schedule->schedule_users()->create(['user_id' => $participant->id, 'status' => ScheduleStatus::Pending]);

        $approver = $this->approver();
        Sanctum::actingAs($approver);
        $this->postJson("/api/admin/schedules/{$schedule->id}/approve")->assertOk();

        Notification::assertSentTo($owner, ScheduleStatusChanged::class);
        Notification::assertSentTo($participant, ScheduleStatusChanged::class);
        Notification::assertNotSentTo($approver, ScheduleStatusChanged::class);
    }

    public function test_stored_notification_is_in_the_recipients_language_and_filament_format(): void
    {
        $owner = $this->user(['locale' => 'pt']);
        $schedule = $this->pendingSchedule($owner);

        Sanctum::actingAs($this->approver());
        $this->postJson("/api/admin/schedules/{$schedule->id}/approve", [], ['Accept-Language' => 'en'])->assertOk();

        $stored = $owner->notifications()->first();
        $this->assertNotNull($stored);
        $this->assertSame('O seu agendamento foi aprovado', $stored->data['title']);
        $this->assertSame('success', $stored->data['status']);
        $this->assertSame('filament', $stored->data['format']);
        $this->assertSame('/my-agenda', $stored->data['path']);
    }

    public function test_mail_is_written_in_the_recipients_language(): void
    {
        $owner = $this->user(['locale' => 'en', 'email_notification' => true]);
        $schedule = $this->pendingSchedule($owner);
        $schedule->status = ScheduleStatus::Approved;

        // The default app language is pt; the mail must still come out in the owner's English
        $owner->notify(new ScheduleStatusChanged($schedule));

        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('Your schedule was approved', $email->getSubject());
        $this->assertStringContainsString('Cleaning', $email->getTextBody() . $email->getHtmlBody());
        $this->assertSame($owner->email, $email->getTo()[0]->getAddress());
    }

    public function test_status_change_by_the_panel_style_update_also_notifies(): void
    {
        Notification::fake();
        $owner = $this->user();
        $schedule = $this->pendingSchedule($owner);

        // what a Filament action does: a plain model update
        $schedule->update(['status' => ScheduleStatus::Approved]);

        Notification::assertSentTo($owner, ScheduleStatusChanged::class);
    }

    public function test_push_is_sent_only_with_the_flag_on_and_a_subscription(): void
    {
        $withPush = $this->user(['push_notification' => true]);
        $withPush->updatePushSubscription('https://push.example/1', 'p256dh-key', 'auth-key');
        $flagOff = $this->user(['push_notification' => false]);
        $flagOff->updatePushSubscription('https://push.example/2', 'p256dh-key', 'auth-key');
        $noDevice = $this->user(['push_notification' => true]);

        Notification::fake();

        foreach ([$withPush, $flagOff, $noDevice] as $user) {
            $user->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => ScheduleStatusChanged::class,
                'data' => ['title' => 'T', 'body' => 'B', 'path' => '/my-agenda'],
            ]);
        }

        Notification::assertSentTo($withPush, PwaWebPushNotification::class);
        Notification::assertNotSentTo($flagOff, PwaWebPushNotification::class);
        Notification::assertNotSentTo($noDevice, PwaWebPushNotification::class);
    }

    public function test_preferences_can_be_changed_through_the_api(): void
    {
        $user = $this->user(['email_notification' => false, 'push_notification' => true]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/user', ['email_notification' => true, 'push_notification' => false])
            ->assertOk()
            ->assertJsonPath('user.email_notification', true)
            ->assertJsonPath('user.push_notification', false);

        $this->assertTrue($user->fresh()->email_notification);
        $this->assertFalse($user->fresh()->push_notification);

        $this->patchJson('/api/user', ['email_notification' => 'maybe'])->assertStatus(422);
    }

    public function test_notification_list_and_read_endpoints(): void
    {
        $user = $this->user();
        $other = $this->user();
        $mine = $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x',
            'data' => ['title' => 'Hello', 'body' => '<b>Body</b>', 'status' => 'success', 'path' => '/my-agenda'],
        ]);
        $theirs = $other->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'data' => ['title' => 'Private'],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Hello')
            ->assertJsonPath('data.0.body', 'Body');

        $this->postJson("/api/notifications/{$theirs->id}/read")->assertNotFound();
        $this->postJson("/api/notifications/{$mine->id}/read")->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('unread', 0);
    }

    public function test_push_subscription_endpoints_and_vapid_key(): void
    {
        config(['webpush.vapid.public_key' => 'PUBLIC-KEY']);
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->getJson('/api/pwa/vapid-public-key')->assertOk()->assertJsonPath('key', 'PUBLIC-KEY');

        $this->postJson('/api/pwa/subscribe', [
            'endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'k1', 'auth' => 'k2'],
        ])->assertOk();
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->deleteJson('/api/pwa/subscribe', ['endpoint' => 'https://push.example/abc'])->assertOk();
        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_notification_endpoints_need_an_approved_account(): void
    {
        Sanctum::actingAs($this->user(['approved_at' => null]));

        $this->getJson('/api/notifications')->assertStatus(403)->assertJsonPath('code', 'account_pending');
    }
}
