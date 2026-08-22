<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Services\Sos\SosAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SOS trigger for the AtlasGo mobile app — mirrors
 * App\Http\Controllers\StudentPortal\SosAlertController::trigger() exactly,
 * calling the same SosAlertService, just resolving the student via the
 * Sanctum-authenticated request instead of a web session.
 */
class StudentSosController extends Controller
{
    /**
     * GET /api/mobile/student/portal/sos/config
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'hold_confirm_seconds' => config('sos.hold_confirm_seconds'),
            'countdown_seconds' => config('sos.countdown_seconds'),
            'emergency_hotline_number' => config('sos.emergency_hotline_number'),
        ]);
    }

    /**
     * POST /api/mobile/student/portal/sos/trigger
     */
    public function trigger(Request $request, SosAlertService $service): JsonResponse
    {
        $validated = $request->validate([
            'alert_type' => 'required|in:medical,security,fire_disaster,general',
            'is_silent' => 'boolean',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric',
        ]);

        $result = $service->trigger(
            triggerable: $request->user(),
            alertType: $validated['alert_type'],
            isSilent: $validated['is_silent'] ?? false,
            lat: $validated['lat'] ?? null,
            lng: $validated['lng'] ?? null,
            accuracy: $validated['accuracy'] ?? null,
            ip: $request->ip(),
        );

        if ($result['blocked']) {
            return response()->json([
                'blocked' => true,
                'message' => config('sos.off_campus_message'),
                'emergency_hotline' => config('sos.emergency_hotline_number'),
            ], 422);
        }

        return response()->json(['blocked' => false, 'alert_id' => $result['alert']->id], 201);
    }
}
