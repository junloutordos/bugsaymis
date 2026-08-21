<?php

namespace App\Http\Controllers\Sos;

use App\Http\Controllers\Controller;
use App\Models\Sos\SosAlert;
use App\Services\Sos\SosAlertService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SosAlertController extends Controller
{
    public function trigger(Request $request, SosAlertService $service)
    {
        $validated = $request->validate([
            'alert_type' => 'required|in:medical,security,fire_disaster,general',
            'is_silent'  => 'boolean',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'accuracy'   => 'nullable|numeric',
        ]);

        $result = $service->trigger(
            triggerable: $request->user(),
            alertType:   $validated['alert_type'],
            isSilent:    $validated['is_silent'] ?? false,
            lat:         $validated['lat'] ?? null,
            lng:         $validated['lng'] ?? null,
            accuracy:    $validated['accuracy'] ?? null,
            ip:          $request->ip(),
        );

        if ($result['blocked']) {
            return response()->json([
                'blocked'           => true,
                'message'           => config('sos.off_campus_message'),
                'emergency_hotline' => config('sos.emergency_hotline_number'),
            ], 422);
        }

        return response()->json(['blocked' => false, 'alert_id' => $result['alert']->id], 201);
    }

    public function index()
    {
        $alerts = SosAlert::with(['events' => fn ($q) => $q->orderByDesc('created_at')])
            ->orderByDesc('triggered_at')
            ->limit(100)
            ->get()
            ->map(fn (SosAlert $alert) => $this->serialize($alert));

        return Inertia::render('Sos/CommandCenter', ['alerts' => $alerts]);
    }

    public function show(SosAlert $alert)
    {
        return response()->json($this->serialize($alert->load('events')));
    }

    public function acknowledge(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $service->acknowledge($alert, $request->user());
        return response()->json($this->serialize($alert->fresh()));
    }

    public function verify(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $validated = $request->validate(['note' => 'nullable|string|max:1000']);
        $service->verify($alert, $request->user(), $validated['note'] ?? null);
        return response()->json($this->serialize($alert->fresh()));
    }

    public function falseAlarm(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $validated = $request->validate(['reason' => 'required|string|max:1000']);
        $service->markFalseAlarm($alert, $request->user(), $validated['reason']);
        return response()->json($this->serialize($alert->fresh()));
    }

    public function resolve(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $validated = $request->validate(['notes' => 'nullable|string|max:2000']);
        $service->resolve($alert, $request->user(), $validated['notes'] ?? null);
        return response()->json($this->serialize($alert->fresh()));
    }

    private function serialize(SosAlert $alert): array
    {
        return [
            'id'           => $alert->id,
            'alert_type'   => $alert->alert_type,
            'is_silent'    => $alert->is_silent,
            'status'       => $alert->status,
            'lat'          => $alert->lat,
            'lng'          => $alert->lng,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
            'resolved_at'  => $alert->resolved_at?->toIso8601String(),
            'events'       => $alert->relationLoaded('events')
                ? $alert->events->map(fn ($e) => [
                    'type'       => $e->type,
                    'payload'    => $e->payload,
                    'created_at' => $e->created_at->toIso8601String(),
                ])->values()
                : [],
        ];
    }
}
