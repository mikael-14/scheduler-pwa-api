<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * The signed-in user's stored notifications (the same ones the admin panel's bell shows).
 */
class NotificationController extends Controller
{
    /** Notification class → the short "kind" the PWA shows (icon and label). */
    private const KINDS = [
        \App\Notifications\SchedulePendingApproval::class => 'pending_approval',
        \App\Notifications\ParticipationRequested::class => 'pending_approval',
        \App\Notifications\ParticipationDecided::class => 'schedule_status',
        \App\Notifications\ScheduleBackToPending::class => 'schedule_status',
        \App\Notifications\MarkedNotShown::class => 'schedule_status',
        \App\Notifications\NotShownRecorded::class => 'activity',
        // "all notifications": other people's schedules
        \App\Notifications\ScheduleActivity::class => 'activity',
        \App\Notifications\ScheduleStatusChanged::class => 'schedule_status',
        \App\Notifications\ScheduleParticipantAdded::class => 'participant_added',
        \App\Notifications\ScheduleParticipantRemoved::class => 'participant_removed',
        \App\Notifications\ScheduleBookedForYou::class => 'booked_for_you',
        \App\Notifications\ScheduleUpdated::class => 'schedule_updated',
        \App\Notifications\CoverageAlert::class => 'coverage',
        \App\Notifications\CustomNotification::class => 'announcement',
        \App\Notifications\AccountApproved::class => 'account',
    ];

    /** Most notifications one page holds (the PWA loads more as the list scrolls). */
    private const MAX_PER_PAGE = 50;

    /**
     * The latest notifications, a page at a time: `filter=unread` for the unread ones only,
     * `per_page` (default and at most 50), and `cursor` (the previous page's `next_cursor`) for the next page.
     *
     * Pages go by time: the next one holds what is older than the last one shown. So reading some in
     * between moves nothing, and the list keeps User::notifications()'s order (within one second: the
     * urgent coverage summary first), a page never cuts a second in two.
     */
    public function index(Request $request)
    {
        $request->validate([
            'filter' => ['nullable', 'in:all,unread'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'cursor' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ]);

        $user = $request->user();
        $query = fn() => $request->input('filter') === 'unread' ? $user->unreadNotifications() : $user->notifications();
        $perPage = (int) $request->input('per_page', self::MAX_PER_PAGE);

        $notifications = $query()
            ->when($request->input('cursor'), fn($q, $cursor) => $q->where('created_at', '<', $cursor))
            ->limit($perPage)
            ->get();

        $nextCursor = null;
        if ($notifications->count() === $perPage) {
            $last = $notifications->last()->created_at;
            // the rest of that same second, so the next page (older than it) does not skip them
            $notifications = $notifications->concat(
                $query()->where('created_at', $last)->whereKeyNot($notifications->modelKeys())->get()
            );
            if ($query()->where('created_at', '<', $last)->exists()) {
                $nextCursor = $last->format('Y-m-d H:i:s');
            }
        }

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'next_cursor' => $nextCursor,
            'data' => $notifications
                ->map(fn($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? '',
                    // with links (one per day of a coverage summary): the short text, the days are the links
                    'body' => strip_tags((string) ($notification->data['summary'] ?? $notification->data['body'] ?? '')),
                    'links' => array_values(array_filter(
                        (array) ($notification->data['links'] ?? []),
                        fn($link) => is_array($link) && filled($link['label'] ?? null) && filled($link['path'] ?? null),
                    )),
                    'status' => $notification->data['status'] ?? 'info',
                    // what it is about, so the app can show an icon and a label per kind
                    'kind' => $notification->type === \App\Notifications\ScheduleJoinedOrLeft::class
                        ? (($notification->data['joined'] ?? true) ? 'participant_added' : 'participant_removed')
                        : (self::KINDS[$notification->type] ?? 'other'),
                    'path' => $notification->data['path'] ?? '/notifications',
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ]),
        ]);
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if (! $notification) {
            return response()->json(['message' => __('Notification not found.')], 404);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'ok']);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'ok']);
    }

    /** Marks the chosen notifications (`ids`) as read; ids that are not the user's are ignored. */
    public function readMany(Request $request)
    {
        $request->user()->unreadNotifications()->whereKey($this->ids($request))->update(['read_at' => now()]);

        return $this->unreadCount($request);
    }

    /** Marks the chosen notifications (`ids`) as unread again; ids that are not the user's are ignored. */
    public function unreadMany(Request $request)
    {
        $request->user()->readNotifications()->whereKey($this->ids($request))->update(['read_at' => null]);

        return $this->unreadCount($request);
    }

    private function ids(Request $request): array
    {
        return $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'string', 'max:64'],
        ])['ids'];
    }

    private function unreadCount(Request $request)
    {
        return response()->json(['message' => 'ok', 'unread' => $request->user()->unreadNotifications()->count()]);
    }
}
