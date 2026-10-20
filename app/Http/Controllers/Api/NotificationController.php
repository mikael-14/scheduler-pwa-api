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
        \App\Notifications\ScheduleStatusChanged::class => 'schedule_status',
        \App\Notifications\ScheduleParticipantAdded::class => 'participant_added',
        \App\Notifications\ScheduleParticipantRemoved::class => 'participant_removed',
        \App\Notifications\ScheduleBookedForYou::class => 'booked_for_you',
        \App\Notifications\ScheduleUpdated::class => 'schedule_updated',
        \App\Notifications\CoverageAlert::class => 'coverage',
        \App\Notifications\CustomNotification::class => 'announcement',
        \App\Notifications\AccountApproved::class => 'account',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'data' => $user->notifications()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? '',
                    'body' => strip_tags((string) ($notification->data['body'] ?? '')),
                    'status' => $notification->data['status'] ?? 'info',
                    // what it is about, so the app can show an icon and a label per kind
                    'kind' => self::KINDS[$notification->type] ?? 'other',
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
}
