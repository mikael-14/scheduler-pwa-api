<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use App\Notifications\PwaWebPushNotification;
use App\Notifications\ScheduleBookedForYou;
use App\Notifications\ScheduleParticipantAdded;
use App\Notifications\ScheduleParticipantRemoved;
use App\Notifications\SchedulePendingApproval;
use App\Notifications\ScheduleStatusChanged;
use App\Notifications\ScheduleUpdated;
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

    public function test_new_schedule_tells_approvers_it_waits_and_participants_they_are_in_it(): void
    {
        $approver = $this->userWithPermissions(['approve_schedule']);
        $member = $this->userWithPermissions(['view_any_schedule', 'create_schedule'], ['name' => 'Rita']);
        $friend = $this->user(['locale' => 'pt']);
        $inactive = $this->user(['status' => false]);
        $pending = $this->user(['approved_at' => null]);
        $type = ScheduleType::create(['name' => 'Passeios', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');

        Notification::fake();
        Sanctum::actingAs($member);
        $id = $this->postJson('/api/admin/schedules', [
            'schedule_type_id' => $type->id, 'start' => "$day 10:00", 'end' => "$day 12:00",
            'schedule_users' => [['user_id' => $friend->id], ['user_id' => $inactive->id], ['user_id' => $pending->id]],
        ])->assertOk()->json('data.id');

        Notification::assertSentTo($approver, SchedulePendingApproval::class);
        Notification::assertSentTo($friend, ScheduleParticipantAdded::class, function (ScheduleParticipantAdded $notification) use ($friend, $id) {
            $data = $notification->toDatabase($friend);

            return $data['title'] === 'Foi adicionado a um agendamento'
                && str_contains($data['body'], 'Passeios')
                && str_contains($data['body'], 'de Rita')
                && str_contains($data['body'], 'Aguarda aprovação.')
                && $data['path'] === "/schedules/{$id}";
        });
        Notification::assertNotSentTo($inactive, ScheduleParticipantAdded::class);
        Notification::assertNotSentTo($pending, ScheduleParticipantAdded::class);
        // the owner is not a participant of their own schedule
        Notification::assertNotSentTo($member, ScheduleParticipantAdded::class);
    }

    public function test_only_people_newly_added_to_a_schedule_are_told(): void
    {
        $owner = $this->userWithPermissions(['view_any_schedule', 'update_schedule']);
        $first = $this->user();
        $second = $this->user();
        $schedule = $this->pendingSchedule($owner);
        $schedule->schedule_users()->create(['user_id' => $first->id, 'status' => ScheduleStatus::Pending]);

        Notification::fake();
        Sanctum::actingAs($owner);
        $this->patchJson("/api/admin/schedules/{$schedule->id}", [
            'schedule_users' => [['user_id' => $first->id], ['user_id' => $second->id]],
        ])->assertOk();

        Notification::assertSentTo($second, ScheduleParticipantAdded::class);
        Notification::assertNotSentTo($first, ScheduleParticipantAdded::class);
    }

    public function test_a_schedule_booked_for_someone_else_tells_them_and_one_for_yourself_does_not(): void
    {
        $coordinator = $this->userWithPermissions(['view_any_schedule', 'create_schedule', 'create_any_schedule'], ['name' => 'Coordenação']);
        $volunteer = $this->user(['locale' => 'pt']);
        $type = ScheduleType::create(['name' => 'Passeios', 'color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');
        $slot = ['schedule_type_id' => $type->id, 'start' => "$day 10:00", 'end' => "$day 12:00"];

        Notification::fake();
        Sanctum::actingAs($coordinator);

        $id = $this->postJson('/api/admin/schedules', $slot + ['user_id' => $volunteer->id])->assertOk()->json('data.id');
        Notification::assertSentTo($volunteer, ScheduleBookedForYou::class, function (ScheduleBookedForYou $notification) use ($volunteer, $id) {
            $data = $notification->toDatabase($volunteer);

            return $data['title'] === 'Foi marcado um agendamento para si'
                && str_contains($data['body'], 'de Coordenação')
                && $data['path'] === "/schedules/{$id}";
        });

        // booking for yourself: nothing
        $this->postJson('/api/admin/schedules', $slot)->assertOk();
        Notification::assertNotSentTo($coordinator, ScheduleBookedForYou::class);
    }

    public function test_handing_a_schedule_over_tells_the_new_owner(): void
    {
        $admin = $this->userWithPermissions(['view_any_schedule', 'update_schedule', 'update_any_schedule']);
        $before = $this->user();
        $after = $this->user();
        $schedule = $this->pendingSchedule($before);

        Notification::fake();
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/schedules/{$schedule->id}", ['user_id' => $after->id])->assertOk();

        Notification::assertSentTo($after, ScheduleBookedForYou::class);
        Notification::assertNotSentTo($before, ScheduleBookedForYou::class);
    }

    public function test_people_removed_from_a_schedule_are_told_unless_they_left_themselves(): void
    {
        $owner = $this->userWithPermissions(['view_any_schedule', 'update_schedule']);
        $stays = $this->user();
        $removed = $this->user(['locale' => 'pt']);
        $schedule = $this->pendingSchedule($owner);
        foreach ([$stays, $removed] as $participant) {
            $schedule->schedule_users()->create(['user_id' => $participant->id, 'status' => ScheduleStatus::Pending]);
        }

        Notification::fake();
        Sanctum::actingAs($owner);
        $this->patchJson("/api/admin/schedules/{$schedule->id}", ['schedule_users' => [['user_id' => $stays->id]]])->assertOk();

        Notification::assertSentTo($removed, ScheduleParticipantRemoved::class, function (ScheduleParticipantRemoved $notification) use ($removed) {
            $data = $notification->toDatabase($removed);

            return $data['title'] === 'Foi removido de um agendamento' && $data['path'] === '/my-agenda';
        });
        Notification::assertNotSentTo($stays, ScheduleParticipantRemoved::class);
        // the removal is audited one by one, not lost in a bulk delete
        $this->assertSame(1, $schedule->schedule_users()->count());

        // someone taking themselves off: nothing
        $this->actingAs($stays);
        $schedule->schedule_users()->where('user_id', $stays->id)->first()->delete();
        Notification::assertNotSentTo($stays, ScheduleParticipantRemoved::class);
    }

    public function test_changing_a_schedule_tells_owner_and_participants_what_changed(): void
    {
        $admin = $this->userWithPermissions(['view_any_schedule', 'update_schedule', 'update_any_schedule', 'approve_schedule']);
        $owner = $this->user(['locale' => 'pt']);
        $participant = $this->user(['locale' => 'en']);
        $schedule = $this->pendingSchedule($owner);
        $schedule->schedule_users()->create(['user_id' => $participant->id, 'status' => ScheduleStatus::Pending]);
        $day = now()->addDays(5)->format('Y-m-d');

        Notification::fake();
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/schedules/{$schedule->id}", [
            'start' => "$day 14:00", 'end' => "$day 16:00", 'description' => 'Bring a leash',
        ])->assertOk();

        Notification::assertSentTo($owner, ScheduleUpdated::class, function (ScheduleUpdated $notification) use ($owner, $day, $schedule) {
            $data = $notification->toDatabase($owner);
            $newStart = \Illuminate\Support\Carbon::parse("$day 14:00")->format(config('app.date_time_format'));

            return $data['title'] === 'Um agendamento seu foi alterado'
                && str_contains($data['body'], "Quando: ")
                && str_contains($data['body'], "→ {$newStart}–16:00")
                && str_contains($data['body'], 'A descrição foi alterada.')
                && $data['path'] === "/schedules/{$schedule->id}";
        });
        // each in their own language
        Notification::assertSentTo($participant, ScheduleUpdated::class, function (ScheduleUpdated $notification) use ($participant) {
            // what the sender does for an English-speaking recipient
            $previous = app()->getLocale();
            app()->setLocale($participant->preferredLocale());
            $title = $notification->toDatabase($participant)['title'];
            app()->setLocale($previous);

            return $title === 'A schedule you are in was changed';
        });
        Notification::assertNotSentTo($admin, ScheduleUpdated::class);
    }

    public function test_no_change_notice_for_your_own_edit_a_decision_or_the_internal_note(): void
    {
        $owner = $this->userWithPermissions(['view_any_schedule', 'update_schedule']);
        $participant = $this->user();
        $approver = $this->userWithPermissions(['view_any_schedule', 'update_schedule', 'update_any_schedule', 'approve_schedule']);
        $schedule = $this->pendingSchedule($owner);
        $schedule->schedule_users()->create(['user_id' => $participant->id, 'status' => ScheduleStatus::Pending]);

        Notification::fake();

        // the owner edits their own schedule: the participant is told, the owner is not
        Sanctum::actingAs($owner);
        $this->patchJson("/api/admin/schedules/{$schedule->id}", ['description' => 'New text'])->assertOk();
        Notification::assertNotSentTo($owner, ScheduleUpdated::class);
        Notification::assertSentToTimes($participant, ScheduleUpdated::class, 1);

        // an approval that also edits: only the decision is announced
        Sanctum::actingAs($approver);
        $this->patchJson("/api/admin/schedules/{$schedule->id}", ['status' => 'approved', 'description' => 'Approved text'])->assertOk();
        Notification::assertSentTo($participant, ScheduleStatusChanged::class);
        Notification::assertSentToTimes($participant, ScheduleUpdated::class, 1);

        // the internal note is staff only: nobody is told
        $this->patchJson("/api/admin/schedules/{$schedule->id}", ['internal_note' => 'Watch the dog'])->assertOk();
        Notification::assertSentToTimes($participant, ScheduleUpdated::class, 1);
        Notification::assertNotSentTo($owner, ScheduleUpdated::class);
    }

    public function test_moving_an_approved_schedule_says_it_waits_for_approval_again(): void
    {
        $owner = $this->userWithPermissions(['view_any_schedule', 'update_schedule']);
        $participant = $this->user(['locale' => 'pt']);
        $schedule = $this->pendingSchedule($owner);
        Schedule::withoutEvents(fn() => $schedule->update(['status' => ScheduleStatus::Approved]));
        $schedule->schedule_users()->create(['user_id' => $participant->id, 'status' => ScheduleStatus::Approved]);
        $day = now()->addDays(6)->format('Y-m-d');

        Notification::fake();
        Sanctum::actingAs($owner);
        $this->patchJson("/api/admin/schedules/{$schedule->id}", ['start' => "$day 10:00", 'end' => "$day 12:00"])->assertOk();

        Notification::assertSentTo($participant, ScheduleUpdated::class, fn(ScheduleUpdated $notification) =>
            str_contains($notification->toDatabase($participant)['body'], 'Volta a aguardar aprovação.'));
    }

    public function test_adding_yourself_as_a_participant_sends_you_nothing(): void
    {
        $owner = $this->user();
        $volunteer = $this->user();
        $schedule = $this->pendingSchedule($owner);

        Notification::fake();
        $this->actingAs($volunteer);
        $schedule->schedule_users()->create(['user_id' => $volunteer->id, 'status' => ScheduleStatus::Pending]);

        Notification::assertNotSentTo($volunteer, ScheduleParticipantAdded::class);
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
        $this->assertSame("/schedules/{$schedule->id}", $stored->data['path']);
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

    public function test_bell_entry_is_written_at_once_while_email_and_push_wait_for_the_queue(): void
    {
        $owner = $this->user(['email_notification' => true, 'push_notification' => true]);
        $owner->updatePushSubscription('https://push.example/1', 'p256dh-key', 'auth-key');
        $schedule = $this->pendingSchedule($owner);

        \Illuminate\Support\Facades\Queue::fake();
        Sanctum::actingAs($this->approver());
        $this->postJson("/api/admin/schedules/{$schedule->id}/approve")->assertOk();

        // the fake holds back the sync bell job too; a stored bell entry is what triggers the push copy
        $owner->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => ScheduleStatusChanged::class,
            'data' => ['title' => 'T', 'body' => 'B'],
        ]);

        // in-app: on the sync connection, so written during the request (the fake catches sync too)
        \Illuminate\Support\Facades\Queue::assertPushed(
            \Illuminate\Notifications\SendQueuedNotifications::class,
            fn($job) => $job->channels === ['database'] && $job->connection === 'sync'
        );

        // email and push: on the real queue, nothing sent during the request
        \Illuminate\Support\Facades\Queue::assertPushed(
            \Illuminate\Notifications\SendQueuedNotifications::class,
            fn($job) => $job->notification instanceof ScheduleStatusChanged && $job->channels === ['mail'] && $job->connection === null
        );
        \Illuminate\Support\Facades\Queue::assertPushed(
            \Illuminate\Notifications\SendQueuedNotifications::class,
            fn($job) => $job->notification instanceof PwaWebPushNotification
        );
        $this->assertCount(0, app('mail.manager')->mailer()->getSymfonyTransport()->messages());
    }

    public function test_queued_email_announces_the_decision_even_if_the_schedule_changed_since(): void
    {
        $owner = $this->user(['locale' => 'en', 'email_notification' => true]);
        $schedule = $this->pendingSchedule($owner);
        $schedule->status = ScheduleStatus::Approved;

        // queued: serialized now, the schedule is read again from the database when the worker runs
        $notification = new ScheduleStatusChanged($schedule);
        Schedule::withoutEvents(fn() => $schedule->update(['status' => ScheduleStatus::Pending]));
        $owner->notifyNow(unserialize(serialize($notification)), ['mail']);

        $email = app('mail.manager')->mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame('Your schedule was approved', $email->getSubject());
    }

    public function test_queued_push_is_dropped_if_push_was_turned_off_before_it_runs(): void
    {
        $user = $this->user(['push_notification' => true]);
        $stored = $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => ScheduleStatusChanged::class,
            'data' => ['title' => 'T', 'body' => 'B'],
        ]);
        $push = new PwaWebPushNotification($stored);

        $this->assertSame([\NotificationChannels\WebPush\WebPushChannel::class], $push->via($user));

        $user->update(['push_notification' => false]);
        $this->assertSame([], $push->via($user));
    }

    public function test_new_accounts_start_with_email_and_push_on(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New Volunteer', 'email' => 'new@example.com',
            'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
        ])->assertSuccessful();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->email_notification);
        $this->assertTrue($user->push_notification);
    }

    public function test_account_approved_is_always_emailed_even_with_email_notifications_off(): void
    {
        $user = User::factory()->create(['approved_at' => null, 'locale' => 'en', 'email_notification' => false]);

        $user->notify(new \App\Notifications\AccountApproved());

        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('Your account was approved', $email->getSubject());
        // not "because email notifications are enabled": they are not
        $this->assertStringNotContainsString('email notifications are enabled', $email->getTextBody() . $email->getHtmlBody());
        $this->assertSame(1, $user->notifications()->count());
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

    public function test_each_notification_says_what_kind_it_is_for_the_pwa(): void
    {
        $user = $this->user();
        $kinds = [
            SchedulePendingApproval::class => 'pending_approval',
            ScheduleStatusChanged::class => 'schedule_status',
            ScheduleParticipantAdded::class => 'participant_added',
            ScheduleParticipantRemoved::class => 'participant_removed',
            ScheduleBookedForYou::class => 'booked_for_you',
            \App\Notifications\CustomNotification::class => 'announcement',
            \App\Notifications\AccountApproved::class => 'account',
            \Filament\Notifications\DatabaseNotification::class => 'other',
        ];
        foreach (array_keys($kinds) as $i => $type) {
            $user->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => $type, 'data' => ['title' => "n$i"],
                'created_at' => now()->subMinutes($i),
            ]);
        }

        Sanctum::actingAs($user);
        $listed = collect($this->getJson('/api/notifications')->assertOk()->json('data'))->pluck('kind', 'title');

        foreach (array_values($kinds) as $i => $kind) {
            $this->assertSame($kind, $listed["n$i"]);
        }
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
