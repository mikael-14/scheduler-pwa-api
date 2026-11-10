<?php

namespace App\Notifications;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One email with the news that waited for it (stored notifications with mail_status = digest that
 * the person has not read in the app yet), grouped so it reads at a glance. Sent by
 * `notifications:digest`, in the person's language (User is HasLocalePreference). Not stored: the
 * news it gathers already are.
 */
class EmailDigest extends Notification
{
    /** Beyond this, the email says how many more are in the app. */
    public const MAX_ITEMS = 30;

    /** Which group each kind of news goes in, in the order the groups are shown. */
    private const GROUPS = [
        'decide' => [SchedulePendingApproval::class, ParticipationRequested::class, NotShownRecorded::class],
        'yours' => [ScheduleJoinedOrLeft::class, ScheduleParticipantAdded::class, ScheduleUpdated::class],
        'agenda' => [CoverageAlert::class, ScheduleActivity::class],
        'messages' => [CustomNotification::class],
    ];

    /** @param Collection<int, DatabaseNotification> $items oldest first */
    public function __construct(private readonly Collection $items)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->items->count();
        $mail = (new MailMessage)
            ->subject($count === 1 ? __('Summary: 1 update') : __('Summary: :count updates', ['count' => $count]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($count === 1
                ? __('Here is what happened since the last summary (1 update you have not seen in the app yet).')
                : __('Here is what happened since the last summary (:count updates you have not seen in the app yet).', ['count' => $count]));

        $shown = $this->items->take(self::MAX_ITEMS);
        foreach ($this->grouped($shown) as $group => $items) {
            $mail->line('**' . $this->heading($group) . '**');
            foreach ($items as $item) {
                $mail->line($this->line($item));
            }
        }

        if ($count > $shown->count()) {
            $mail->line(__('And :count more in the app.', ['count' => $count - $shown->count()]));
        }

        return $mail
            ->action(__('Open the scheduler'), $this->pwaUrl('/notifications'))
            ->line(__('You get these summaries because you chose them in your settings. You can get each email right away instead.'));
    }

    /** @return array<string, Collection<int, DatabaseNotification>> */
    private function grouped(Collection $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $group = collect(self::GROUPS)->search(fn(array $types) => in_array($item->type, $types, true)) ?: 'messages';
            $groups[$group][] = $item;
        }

        // in the order of GROUPS
        return collect(array_keys(self::GROUPS))
            ->filter(fn(string $group) => isset($groups[$group]))
            ->mapWithKeys(fn(string $group) => [$group => collect($groups[$group])])
            ->all();
    }

    private function heading(string $group): string
    {
        return match ($group) {
            'decide' => __('To decide'),
            'yours' => __('Your schedules'),
            'agenda' => __('On the agenda'),
            default => __('Messages'),
        };
    }

    /** "[Maria joined your schedule](link) — Passeios · 12/11 10:00" */
    private function line(DatabaseNotification $item): string
    {
        $data = $item->data ?? [];
        // plain text: no brackets that would break the link, the lines of the body on one line
        $title = str_replace(['[', ']'], '', strip_tags((string) ($data['title'] ?? '')));
        $body = Str::limit(trim(preg_replace('/\s*\R\s*/', ' · ', strip_tags((string) ($data['body'] ?? '')))), 200);

        $url = $this->pwaUrl($data['path'] ?? '/notifications');

        // the body already says it all ("Rita juntou-se ao seu agendamento de Passeios, a ..."): only that
        if ($body !== '' && str_starts_with($body, $title)) {
            return '[' . str_replace(['[', ']'], '', $body) . '](' . $url . ')';
        }

        $line = '[' . $title . '](' . $url . ')';

        return $body !== '' ? "{$line} — {$body}" : $line;
    }

    private function pwaUrl(string $path): string
    {
        return rtrim((string) config('app.pwa_url'), '/') . '/' . ltrim($path, '/');
    }
}
