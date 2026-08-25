<?php

namespace App\Http\Controllers\Sos;

use App\Http\Controllers\Controller;
use App\Models\Sos\SosAlert;
use App\Models\Student;
use App\Services\Sos\LocationResolverService;
use App\Services\Sos\SosAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SosAlertController extends Controller
{
    public function __construct(private readonly LocationResolverService $locationResolver) {}

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
        $alerts = SosAlert::with(['events' => fn ($q) => $q->orderByDesc('created_at'), 'triggerable', 'responders.user'])
            ->orderByDesc('triggered_at')
            ->limit(100)
            ->get()
            ->map(fn (SosAlert $alert) => $this->serialize($alert));

        $emergencyAlerts = \App\Models\Sos\EmergencyAlert::orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'message' => $a->message,
                'severity' => $a->severity, 'audience' => $a->audience, 'status' => $a->status,
                'source' => $a->source, 'sos_alert_id' => $a->sos_alert_id,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return Inertia::render('Sos/CommandCenter', ['alerts' => $alerts, 'emergencyAlerts' => $emergencyAlerts, 'authUserId' => auth()->id()]);
    }

    public function show(SosAlert $alert)
    {
        return response()->json($this->serialize($alert->load(['events', 'triggerable', 'responders.user'])));
    }

    public function acknowledge(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $service->acknowledge($alert, $request->user());
        return response()->json($this->serialize($alert->fresh()));
    }

    public function claim(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $service->claim($alert, $request->user());
        return response()->json($this->serialize($alert->fresh(['events', 'triggerable', 'responders.user'])));
    }

    public function unclaim(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $service->unclaim($alert, $request->user());
        return response()->json($this->serialize($alert->fresh(['events', 'triggerable', 'responders.user'])));
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

    public function history(Request $request)
    {
        $validated = $request->validate([
            'from'       => 'nullable|date',
            'to'         => 'nullable|date',
            'alert_type' => 'nullable|in:medical,security,fire_disaster,general',
            'status'     => 'nullable|in:resolved,false_alarm',
            'reporter'   => 'nullable|string|max:255',
        ]);

        $query = SosAlert::with(['events', 'triggerable'])
            ->whereIn('status', ['resolved', 'false_alarm'])
            ->orderByDesc('triggered_at');

        if (! empty($validated['from'])) {
            $query->where('triggered_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('triggered_at', '<=', $validated['to']);
        }
        if (! empty($validated['alert_type'])) {
            $query->where('alert_type', $validated['alert_type']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['reporter'])) {
            $term = '%'.$validated['reporter'].'%';
            $userIds = \App\Models\User::where('name', 'like', $term)->pluck('id');
            $studentIds = DB::table('students')->whereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", [$term])->pluck('id');

            $query->where(function ($q) use ($userIds, $studentIds) {
                $q->where(fn ($q2) => $q2->where('triggerable_type', \App\Models\User::class)->whereIn('triggerable_id', $userIds))
                  ->orWhere(fn ($q2) => $q2->where('triggerable_type', Student::class)->whereIn('triggerable_id', $studentIds));
            });
        }

        return response()->json($query->paginate(20)->through(fn (SosAlert $alert) => $this->serialize($alert)));
    }

    public function stats(Request $request)
    {
        $validated = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $base = SosAlert::query();
        if (! empty($validated['from'])) {
            $base->where('triggered_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $base->where('triggered_at', '<=', $validated['to']);
        }

        $byType = (clone $base)->select('alert_type', DB::raw('count(*) as total'))
            ->groupBy('alert_type')->pluck('total', 'alert_type');

        $byMonth = (clone $base)->select(DB::raw("DATE_FORMAT(triggered_at, '%Y-%m') as month"), DB::raw('count(*) as total'))
            ->groupBy('month')->orderBy('month')->pluck('total', 'month');

        $closedAlerts = (clone $base)->whereIn('status', ['resolved', 'false_alarm'])
            ->whereNotNull('resolved_at')->get(['id', 'triggered_at', 'resolved_at']);

        $avgResolutionMinutes = $closedAlerts->isEmpty() ? null
            : round($closedAlerts->avg(fn (SosAlert $a) => $a->triggered_at->diffInMinutes($a->resolved_at)), 1);

        $alertIds = (clone $base)->pluck('id');
        $triggeredAtByAlert = (clone $base)->pluck('triggered_at', 'id');

        $firstClaimByAlert = \App\Models\Sos\SosAlertEvent::where('type', 'claimed')
            ->whereIn('sos_alert_id', $alertIds)
            ->orderBy('created_at')
            ->get()
            ->unique('sos_alert_id');

        $claimMinutes = $firstClaimByAlert
            ->map(fn ($event) => isset($triggeredAtByAlert[$event->sos_alert_id])
                ? $triggeredAtByAlert[$event->sos_alert_id]->diffInMinutes($event->created_at)
                : null)
            ->filter(fn ($minutes) => $minutes !== null);

        $avgFirstClaimMinutes = $claimMinutes->isEmpty() ? null : round($claimMinutes->avg(), 1);

        return response()->json([
            'by_type'                  => $byType,
            'by_month'                 => $byMonth,
            'avg_first_claim_minutes'  => $avgFirstClaimMinutes,
            'avg_resolution_minutes'   => $avgResolutionMinutes,
        ]);
    }

    private function serialize(SosAlert $alert): array
    {
        $isActive = ! in_array($alert->status, ['resolved', 'false_alarm'], true);

        return [
            'id'           => $alert->id,
            'alert_type'   => $alert->alert_type,
            'is_silent'    => $alert->is_silent,
            'status'       => $alert->status,
            'lat'          => $alert->lat,
            'lng'          => $alert->lng,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
            'resolved_at'  => $alert->resolved_at?->toIso8601String(),
            'resolved_location' => [
                'type'     => $alert->resolved_location_type,
                'label'    => $alert->resolved_location_label,
                'building' => $alert->resolved_building,
                'room'     => $alert->resolved_room,
                'source'   => $alert->resolved_source,
            ],
            'current_location' => $isActive && $alert->relationLoaded('triggerable') && $alert->triggerable
                ? $this->locationResolver->resolve($alert->triggerable, now())
                : null,
            'gps_badge' => $this->locationResolver->gpsBadge($alert->lat, $alert->lng),
            'responders' => $alert->relationLoaded('responders')
                ? $alert->responders->whereNull('unclaimed_at')->values()->map(fn ($r) => [
                    'user_id'    => $r->user_id,
                    'name'       => $r->user->name,
                    'claimed_at' => $r->claimed_at->toIso8601String(),
                ])->values()
                : [],
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
