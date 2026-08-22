<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\Administration\Announcement;
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

        return response()->json([
            'announcements'    => $announcements,
            'emergency_alerts' => [],
        ]);
    }

    public function acknowledge(Request $request, string $type, int $id): JsonResponse
    {
        $recipient = $request->user();
        $group = $recipient instanceof Student ? 'students' : 'parents';

        $notice = match ($type) {
            'announcement' => Announcement::visibleToAudienceGroup($group)->findOrFail($id),
            default        => abort(404),
        };

        $notice->acknowledgeFor($recipient);

        return response()->json(['message' => 'Acknowledged.']);
    }
}
