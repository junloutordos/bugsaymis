<?php

namespace App\Http\Controllers\Sos;

use App\Events\Sos\EmergencyAlertBroadcast;
use App\Events\Sos\EmergencyAlertResolved;
use App\Http\Controllers\Controller;
use App\Jobs\Sos\DispatchEmergencyAlertJob;
use App\Models\Sos\EmergencyAlert;
use App\Models\Sos\SosAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmergencyAlertController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            EmergencyAlert::orderByDesc('created_at')->limit(50)->get()->map(fn ($a) => $this->serialize($a))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $alert = $this->create($this->validated($request), sosAlertId: null);

        return response()->json($this->serialize($alert), 201);
    }

    public function storeFromSos(Request $request, SosAlert $alert): JsonResponse
    {
        $emergencyAlert = $this->create($this->validated($request), sosAlertId: $alert->id);

        return response()->json($this->serialize($emergencyAlert), 201);
    }

    public function resolve(Request $request, EmergencyAlert $emergencyAlert): JsonResponse
    {
        if ($emergencyAlert->isResolved()) {
            return response()->json(['message' => 'This alert is already resolved.'], 422);
        }

        $emergencyAlert->update([
            'status'      => 'resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        event(new EmergencyAlertResolved($this->serialize($emergencyAlert->fresh())));

        return response()->json($this->serialize($emergencyAlert->fresh()));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'    => 'required|string|max:255',
            'message'  => 'required|string|max:5000',
            'severity' => 'required|in:info,warning,critical',
            'audience' => 'required|in:all,employees,students,parents',
        ]);
    }

    private function create(array $data, ?int $sosAlertId): EmergencyAlert
    {
        $alert = EmergencyAlert::create([
            ...$data,
            'source'       => $sosAlertId ? 'escalated' : 'manual',
            'sos_alert_id' => $sosAlertId,
            'created_by'   => auth()->id(),
            'status'       => 'active',
        ]);

        event(new EmergencyAlertBroadcast($this->serialize($alert)));
        DispatchEmergencyAlertJob::dispatch($alert->id);

        return $alert;
    }

    private function serialize(EmergencyAlert $alert): array
    {
        return [
            'id'           => $alert->id,
            'title'        => $alert->title,
            'message'      => $alert->message,
            'severity'     => $alert->severity,
            'audience'     => $alert->audience,
            'status'       => $alert->status,
            'source'       => $alert->source,
            'sos_alert_id' => $alert->sos_alert_id,
            'created_at'   => $alert->created_at->toIso8601String(),
            'resolved_at'  => $alert->resolved_at?->toIso8601String(),
        ];
    }
}
