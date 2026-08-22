<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\Sos\SosAlert;
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

    /**
     * GET /api/mobile/student/portal/sos/{alert}
     */
    public function show(Request $request, SosAlert $alert): JsonResponse
    {
        $user = $request->user();
        if ($alert->triggerable_type !== get_class($user) || $alert->triggerable_id !== $user->getKey()) {
            abort(403);
        }

        return response()->json($this->serialize($alert->load('events')));
    }

    /**
     * POST /api/mobile/student/portal/sos/{alert}/end
     */
    public function end(Request $request, SosAlert $alert, SosAlertService $service): JsonResponse
    {
        $user = $request->user();
        if ($alert->triggerable_type !== get_class($user) || $alert->triggerable_id !== $user->getKey()) {
            abort(403);
        }

        try {
            $service->endByReporter($alert, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($this->serialize($alert->fresh()->load('events')));
    }

    private function serialize(SosAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'alert_type' => $alert->alert_type,
            'is_silent' => $alert->is_silent,
            'status' => $alert->status,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'events' => $alert->relationLoaded('events')
                ? $alert->events->map(fn ($e) => [
                    'type' => $e->type,
                    'payload' => $e->payload,
                    'created_at' => $e->created_at->toIso8601String(),
                ])->values()
                : [],
        ];
    }
}
