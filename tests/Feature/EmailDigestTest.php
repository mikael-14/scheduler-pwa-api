<?php

namespace Tests\Feature;

use App\Enums\DigestFrequency;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\ScheduleType;
use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\ScheduleJoinedOrLeft;
use App\Notifications\ScheduleStatusChanged;
use App\Notifications\ScheduleUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The emails that can wait go out together in a summary (EmailDigest, notifications:digest);
 * decisions about the person and account emails still go out right away.
 */
class EmailDigestTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['approved_at' => now(), 'status' => true, 'locale' => 'pt'], $attributes));
    }

    private function schedule(User $owner): Schedule
    {
        $type = ScheduleType::firstOrCreate(['name' => 'Passeios'], ['color' => '#112233', 'status' => true, 'range' => true, 'all_day' => false]);
        $day = now()->addDays(3)->format('Y-m-d');
        $schedule = new Schedule([
            'schedule_type_id' => $type->id, 'user_id' => $owner->id, 'status' => ScheduleStatus::Approved,
            'start' => "$day 10:00", 'end' => "$day 12:00",
        ]);
        $schedule->saveQuietly();

        return $schedule->load(['user', 'schedule_type']);
    }

    /** @return \Illuminate\Support\Collection<int, \Symfony\Component\Mime\Email> */
    private function emails()
    {
        return app('mail.manager')->mailer()->getSymfonyTransport()->messages()->map->getOriginalMessage();
    }

    private function joined(User $owner, string $name): void
    {
        $owner->notify(new ScheduleJoinedOrLeft($this->schedule($owner), $this->user(['name' => $name]), joined: true));
    }

    public function test_news_that_can_wait_is_kept_for_the_summary_while_decisions_go_out_at_once(): void
    {
        $owner = $this->user(['name' => 'Olga']);
        $this->assertSame(DigestFrequency::Twice, $owner->email_digest); // the default

        $this->joined($owner, 'Maria');
        $this->assertCount(0, $this->emails());
        $this->assertSame('digest', $owner->notifications()->first()->mail_status);

        // about them: right away, whatever they chose
        $owner->notify(new ScheduleStatusChanged($this->schedule($owner)));
        $owner->notify(new AccountApproved());
        $this->assertCount(2, $this->emails());

        // who chose each email right away gets it at once
        $instant = $this->user(['email_digest' => 'instant']);
        $this->joined($instant, 'Rui');
        $this->assertCount(3, $this->emails());
        $this->assertSame('sent', $instant->notifications()->first()->mail_status);
    }

    public function test_the_summary_gathers_what_was_not_read_in_the_app_and_is_sent_once(): void
    {
        config(['app.pwa_url' => 'https://pwa.test']);
        $owner = $this->user(['name' => 'Olga']);
        $this->joined($owner, 'Maria');
        $this->joined($owner, 'Rui');
        $owner->notify(new ScheduleUpdated($this->schedule($owner), ['description']));

        // seen in the app: left out (and not emailed later either)
        $read = $owner->notifications()->where('data->title', 'like', '%Rui%')->first();
        $read->markAsRead();

        $this->artisan('notifications:digest')->assertSuccessful();

        $emails = $this->emails();
        $this->assertCount(1, $emails);
        $email = $emails->first();
        $this->assertSame('Resumo: 2 novidades', $email->getSubject());
        $html = $email->getHtmlBody();
        $this->assertStringContainsString('Os seus agendamentos', $html);
        $this->assertStringContainsString('Maria juntou-se ao seu agendamento', $html);
        $this->assertStringNotContainsString('Rui', $html);
        $this->assertStringContainsString('https://pwa.test/schedules/', $html);

        $this->assertSame(['sent', 'sent'], $owner->notifications()->whereNull('read_at')->get()->map(fn($n) => $n->mail_status)->all());
        $this->assertNull($read->fresh()->mail_status);

        // nothing new: no second summary
        $this->artisan('notifications:digest')->assertSuccessful();
        $this->assertCount(1, $this->emails());
    }

    public function test_once_a_day_people_only_get_the_morning_summary(): void
    {
        $daily = $this->user(['email_digest' => 'daily']);
        $this->joined($daily, 'Maria');

        $this->artisan('notifications:digest')->assertSuccessful(); // 18:00
        $this->assertCount(0, $this->emails());

        $this->artisan('notifications:digest --with-daily')->assertSuccessful(); // 08:00
        $this->assertCount(1, $this->emails());
        $this->assertSame('Resumo: 1 novidade', $this->emails()->first()->getSubject());
    }

    public function test_turning_email_off_drops_what_was_waiting(): void
    {
        $owner = $this->user();
        $this->joined($owner, 'Maria');
        $owner->update(['email_notification' => false, 'all_email_notification' => false]);

        $this->artisan('notifications:digest --with-daily')->assertSuccessful();

        $this->assertCount(0, $this->emails());
        $this->assertNull($owner->notifications()->first()->mail_status);
    }

    public function test_the_choice_is_saved_through_the_api(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        // and sent back, so the app shows the choice
        $this->patchJson('/api/user', ['email_digest' => 'daily'])->assertOk()->assertJsonPath('user.email_digest', 'daily');
        $this->assertSame(DigestFrequency::Daily, $user->fresh()->email_digest);

        $this->patchJson('/api/user', ['email_digest' => 'weekly'])->assertStatus(422)->assertJsonValidationErrors('email_digest');
    }
}
