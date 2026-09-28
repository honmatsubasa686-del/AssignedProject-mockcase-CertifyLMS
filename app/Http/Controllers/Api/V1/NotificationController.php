<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($notification): array {
                $data = is_array($notification->data)
                    ? $notification->data
                    : [];

                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? '通知',
                    'message' => $data['message'] ?? ($data['body_preview'] ?? ''),
                    'url' => $data['url'] ?? route('notifications.index'),
                    'created_at' => $notification->created_at?->toISOString(),
                    'created_relative' => $notification->created_at?->diffForHumans() ?? '',
                    'is_unread' => $notification->read_at === null,
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'notification_id' => $notification->id,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'unread_count' => 0,
        ]);
    }
}
