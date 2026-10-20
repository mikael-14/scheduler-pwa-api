<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Filament\Resources\NotificationLogs\Pages\ListNotificationLogs;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\CustomNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Admins write announcements to everyone, to some roles or to chosen people. */
class SendNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = [], ?string $role = null): User
    {
        $user = User::factory()->create(array_merge([
            'approved_at' => now(), 'status' => true, 'email_notification' => false, 'push_notification' => false,
        ], $attributes));

        if ($role) {
            $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        }

        return $user;
    }

    private function admin(array $permissions = ['view_any_notification_log', 'view_notification_log', 'send_notification_log']): User
    {
        $admin = $this->user(['name' => 'Admin Sender']);
        $role = Role::firstOrCreate(['name' => 'notifier', 'guard_name' => 'web']);
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        return $admin;
    }

    /** @return \Illuminate\Support\Collection<int, int> ids of the people who got the announcement */
    private function receivedBy(): \Illuminate\Support\Collection
    {
        return NotificationLog::where('type', CustomNotification::class)->pluck('notifiable_id')->sort()->values();
    }

    #[RunInSeparateProcess]
    public function test_announcement_to_a_role_reaches_only_its_active_members(): void
    {
        $volunteer = $this->user([], 'volunteer');
        $other = $this->user([], 'coordinator');
        $this->user(['status' => false], 'volunteer');          // inactive
        $this->user(['approved_at' => null], 'volunteer');      // still pending
        $admin = $this->admin();

        Livewire::test(ListNotificationLogs::class)
            ->callAction('send', [
                'audience' => 'roles',
                'roles' => [Role::findByName('volunteer')->id],
                'subject' => 'Shelter closed on Monday',
                'message' => "Cleaning day.\nSee you on Tuesday!",
                'level' => 'warning',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame([$volunteer->id], $this->receivedBy()->all());

        $log = NotificationLog::where('notifiable_id', $volunteer->id)->firstOrFail();
        $this->assertSame('Shelter closed on Monday', $log->data['title']);
        $this->assertSame("Cleaning day.\nSee you on Tuesday!", $log->data['body']);
        $this->assertSame('warning', $log->data['status']);
        $this->assertSame($admin->id, $log->sender_id);
        // email off for this person and not marked as important
        $this->assertNull($log->mail_status);
        $this->assertNotContains($other->id, $this->receivedBy()->all());
    }

    #[RunInSeparateProcess]
    public function test_important_announcement_to_everyone_is_emailed_even_with_email_off(): void
    {
        $a = $this->user(['email_notification' => false]);
        $b = $this->user(['email_notification' => true]);
        $admin = $this->admin();

        Livewire::test(ListNotificationLogs::class)
            ->callAction('send', [
                'audience' => 'everyone',
                'subject' => 'Fire drill',
                'message' => 'Tomorrow at 10:00.',
                'level' => 'danger',
                'email_everyone' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(collect([$admin->id, $a->id, $b->id])->sort()->values()->all(), $this->receivedBy()->all());
        $this->assertSame(3, NotificationLog::where('mail_status', DeliveryStatus::Sent->value)->count());

        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(3, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('Fire drill', $email->getSubject());
        $this->assertStringNotContainsString('email notifications are enabled', $email->getHtmlBody());
    }

    #[RunInSeparateProcess]
    public function test_announcement_to_specific_people_and_validation(): void
    {
        $chosen = $this->user();
        $this->user();
        $this->admin();

        Livewire::test(ListNotificationLogs::class)
            ->callAction('send', ['audience' => 'users', 'users' => [], 'subject' => '', 'message' => '', 'level' => 'info'])
            ->assertHasActionErrors(['users' => 'required', 'subject' => 'required', 'message' => 'required']);
        $this->assertSame(0, $this->receivedBy()->count());

        Livewire::test(ListNotificationLogs::class)
            ->callAction('send', ['audience' => 'users', 'users' => [$chosen->id], 'subject' => 'Hi', 'message' => 'Just you.', 'level' => 'info'])
            ->assertHasNoActionErrors();

        $this->assertSame([$chosen->id], $this->receivedBy()->all());
    }

    #[RunInSeparateProcess]
    public function test_the_button_needs_the_send_permission(): void
    {
        $this->admin(['view_any_notification_log', 'view_notification_log']);

        Livewire::test(ListNotificationLogs::class)->assertActionHidden('send');
    }
}
