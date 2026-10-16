<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * The signed-in user's stored notifications (the same ones the admin panel's bell shows).
 */
class NotificationController extends Controller
{
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
