<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Services\StudentProfileChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProfileChangeRequestApiController extends Controller
{
    /**
     * GET /api/mobile/student/portal/profile-update
     */
    public function show(Request $request, StudentProfileChangeRequestService $service): JsonResponse
    {
        $studentId = $request->user()?->id;
        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $pending = $service->pendingRequest($studentId);

        return response()->json([
            'current' => $service->currentValues($studentId),
            'editable_fields' => StudentProfileChangeRequestService::EDITABLE_FIELDS,
            'pending' => $pending ? [
                'requested_changes' => $pending->requested_changes,
                'submitted_at' => $pending->created_at->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * POST /api/mobile/student/portal/profile-update
     */
    public function store(Request $request, StudentProfileChangeRequestService $service): JsonResponse
    {
        $studentId = $request->user()?->id;
        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $changes = $request->input('changes', []);
        $result = $service->submit($studentId, is_array($changes) ? $changes : []);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => 'Update request submitted for registrar review.'], 201);
    }
}
