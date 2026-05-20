<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** GET /api/notifications — latest 20 + unread count */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $items = $user->notifications()->latest()->limit(20)->get()->map(fn ($n) => [
            'id'           => $n->id,
            'request_type' => $n->data['request_type'] ?? '',
            'reference_no' => $n->data['reference_no'] ?? '',
            'status'       => $n->data['status'] ?? '',
            'url'          => $n->data['url'] ?? '#',
            'remarks'      => $n->data['remarks'] ?? null,
            'read_at'      => $n->read_at?->toIso8601String(),
            'created_at'   => $n->created_at->toIso8601String(),
        ]);

        return response()->json([
            'notifications' => $items,
            'unread_count'  => $user->unreadNotifications()->count(),
        ]);
    }

    /** POST /api/notifications/{id}/read */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    /** POST /api/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['ok' => true]);
    }
}
