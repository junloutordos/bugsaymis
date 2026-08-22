<?php

namespace App\Http\Controllers;

use App\Models\Administration\Announcement;
use App\Models\Sos\EmergencyAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoticeController extends Controller
{
    public function pending(): JsonResponse
    {
        $user = Auth::user();

        $announcements = Announcement::visibleTo($user)
            ->get()
            ->reject(fn (Announcement $a) => $a->isAcknowledgedBy($user))
            ->map(fn (Announcement $a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'poster_path'  => $a->poster_path,
                'published_at' => $a->published_at?->toIso8601String(),
            ])
            ->values();

        $emergencyAlerts = EmergencyAlert::visibleTo($user)
            ->get()
            ->reject(fn (EmergencyAlert $a) => $a->isAcknowledgedBy($user))
            ->map(fn (EmergencyAlert $a) => [
                'id'       => $a->id,
                'title'    => $a->title,
                'message'  => $a->message,
                'severity' => $a->severity,
            ])
            ->values();

        return response()->json([
            'announcements'    => $announcements,
            'emergency_alerts' => $emergencyAlerts,
        ]);
    }

    public function acknowledge(Request $request, string $type, int $id): JsonResponse
    {
        $user = Auth::user();

        $notice = match ($type) {
            'announcement'    => Announcement::visibleTo($user)->findOrFail($id),
            'emergency-alert' => EmergencyAlert::visibleTo($user)->findOrFail($id),
            default           => abort(404),
        };

        $notice->acknowledgeFor($user);

        return response()->json(['message' => 'Acknowledged.']);
    }
}
