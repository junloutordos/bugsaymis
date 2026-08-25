<?php

namespace App\Http\Controllers\Sos;

use App\Http\Controllers\Controller;
use App\Models\Sos\SosAlert;
use App\Services\Sos\SosAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web/Atlas mirror of StudentAttendance\Api\StudentSosController::show()/end() —
 * lets any reporter (User) who triggered their own SosAlert poll its status and
 * stand it down themselves, matching what AtlasGo already offers students.
 */
class SosSelfServiceController extends Controller
{
    public function status(Request $request, SosAlert $alert): JsonResponse
    {
        $this->authorizeOwnership($request, $alert);

        return response()->json($this->serialize($alert->load('events')));
    }

    public function end(Request $request, SosAlert $alert, SosAlertService $service): JsonResponse
    {
        $this->authorizeOwnership($request, $alert);

        try {
            $service->endByReporter($alert, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($this->serialize($alert->fresh()->load('events')));
    }

    private function authorizeOwnership(Request $request, SosAlert $alert): void
    {
        $user = $request->user();
        if ($alert->triggerable_type !== get_class($user) || $alert->triggerable_id !== $user->getKey()) {
            abort(403);
        }
    }

    private function serialize(SosAlert $alert): array
    {
        return [
            'id'                       => $alert->id,
            'alert_type'               => $alert->alert_type,
            'status'                   => $alert->status,
            'triggered_at'             => $alert->triggered_at->toIso8601String(),
            'resolved_at'              => $alert->resolved_at?->toIso8601String(),
            'resolved_location_type'   => $alert->resolved_location_type,
            'resolved_location_label'  => $alert->resolved_location_label,
            'events'                   => $alert->events->map(fn ($e) => [
                'type'       => $e->type,
                'created_at' => $e->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
