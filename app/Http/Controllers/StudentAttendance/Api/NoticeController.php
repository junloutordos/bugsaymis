<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\Administration\Announcement;
use App\Models\Sos\EmergencyAlert;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $recipient = $request->user();
        $group = $recipient instanceof Student ? 'students' : 'parents';

        $announcements = Announcement::visibleToAudienceGroup($group)
            ->get()
            ->reject(fn (Announcement $a) => $a->isAcknowledgedBy($recipient))
            ->map(fn (Announcement $a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'poster_path'  => $a->poster_path,
                'published_at' => $a->published_at?->toIso8601String(),
            ])
            ->values();

        $emergencyAlerts = EmergencyAlert::visibleToAudienceGroup($group)
            ->get()
            ->reject(fn (EmergencyAlert $a) => $a->isAcknowledgedBy($recipient))
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

    public function history(Request $request): JsonResponse
    {
        $recipient = $request->user();
        $group = $recipient instanceof Student ? 'students' : 'parents';

        $paginator = Announcement::visibleToAudienceGroup($group)
            ->with(['acknowledgments' => fn ($q) => $q
                ->where('recipient_type', get_class($recipient))
                ->where('recipient_id', $recipient->getKey())])
            ->orderByDesc('published_at')
            ->paginate(15);

        $paginator->getCollection()->transform(fn (Announcement $a) => [
            'id'           => $a->id,
            'title'        => $a->title,
            'body'         => $a->body,
            'poster_path'  => $a->poster_path,
            'published_at' => $a->published_at?->toIso8601String(),
            'is_read'      => $a->acknowledgments->isNotEmpty(),
        ]);

        return response()->json($paginator);
    }

    public function acknowledge(Request $request, string $type, int $id): JsonResponse
    {
        $recipient = $request->user();
        $group = $recipient instanceof Student ? 'students' : 'parents';

        $notice = match ($type) {
            'announcement'    => Announcement::visibleToAudienceGroup($group)->findOrFail($id),
            'emergency-alert' => EmergencyAlert::visibleToAudienceGroup($group)->findOrFail($id),
            default           => abort(404),
        };

        $notice->acknowledgeFor($recipient);

        return response()->json(['message' => 'Acknowledged.']);
    }
}
