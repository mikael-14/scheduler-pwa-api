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
        $subject = $count === 1 ? __('Summary: 1 update') : __('Summary: :count updates', ['count' => $count]);
        $shown = $this->items->take(self::MAX_ITEMS);

        // a list per group, a row per piece of news with its colour and link
        $sections = collect($this->grouped($shown))
            ->map(fn(Collection $items, string $group) => [
                'heading' => $this->heading($group),
                'items' => $items->map(fn(DatabaseNotification $item) => $this->item($item))->values()->all(),
            ])
            ->values()
            ->all();

        $mail = (new MailMessage)
            ->markdown('notifications::email', [
                'status' => 'info',
                'heading' => $subject, // "Summary: 4 updates"
                'sections' => $sections,
            ])
            ->subject($subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($count === 1
                ? __('Here is what happened since the last summary (1 update you have not seen in the app yet).')
                : __('Here is what happened since the last summary (:count updates you have not seen in the app yet).', ['count' => $count]))
            ->action(__('Open the scheduler'), $this->pwaUrl('/notifications'));

        if ($count > $shown->count()) {
            $mail->line(__('And :count more in the app.', ['count' => $count - $shown->count()]));
        }

        return $mail->line(__('You get these summaries because you chose them in your settings. You can get each email right away instead.'));
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

    /** A row: "Maria joined your schedule" / "Passeios · 12/11 10:00" [Open], in its colour. */
    private function item(DatabaseNotification $item): array
    {
        $data = $item->data ?? [];
        // plain text, the lines of the body on one line
        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        $body = Str::limit($this->oneLine(strip_tags((string) ($data['body'] ?? ''))), 200);

        // the body already says it all ("Rita juntou-se ao seu agendamento de Passeios, a ..."): only that
        if ($body !== '' && str_starts_with($body, $title)) {
            [$title, $body] = [$body, ''];
        }

        return [
            'label' => $title !== '' ? $title : $body,
            'meta' => $title !== '' && $body !== '' ? $body : null,
            'url' => $this->pwaUrl($data['path'] ?? '/notifications'),
            'link' => __('Open'),
            'status' => $data['status'] ?? 'info',
        ];
    }

    /**
     * The body's lines on one line, bullets dropped: "Nobody is approved yet for these days:
     * Friday, 27 November · Saturday, 28 November".
     */
    private function oneLine(string $text): string
    {
        $line = '';
        foreach (preg_split('/\R/', $text) as $part) {
            $part = trim(preg_replace('/^[•\-*]\s*/u', '', trim($part)));
            if ($part === '') {
                continue;
            }
            $line .= $line === '' ? $part : (str_ends_with($line, ':') ? ' ' : ' · ') . $part;
        }

        return $line;
    }

    private function pwaUrl(string $path): string
    {
        return rtrim((string) config('app.pwa_url'), '/') . '/' . ltrim($path, '/');
    }
}
