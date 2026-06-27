<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhIncident;
use App\Models\ResidenceHall\RhIntern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RhIncidentController extends Controller
{
    private function hallScope(): ?string
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return null;
        return $user->rh_hall ?: null;
    }

    public function index(Request $request)
    {
        $this->authorize('rh.incidents.manage');

        $hall   = $this->hallScope();
        $type   = $request->input('type', '');
        $status = $request->input('status', '');
        $search = $request->input('search', '');

        $incidents = RhIncident::with(['intern.room', 'loggedBy'])
            ->whereHas('intern', function ($q) use ($hall) {
                if ($hall) $q->whereHas('room', fn($r) => $r->where('residence_hall', $hall));
            })
            ->when($type, fn($q) => $q->where('incident_type', $type))
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($inc) use ($search) {
                $resolver = app(\App\Services\Discipline\StudentResolver::class);
                $student  = $resolver->resolve($inc->intern->student_id ?? 0);
                $name     = $student['name'] ?? ('Student #' . $inc->intern->student_id);
                if ($search && !str_contains(strtolower($name), strtolower($search))) return null;
                return [
                    'id'                   => $inc->id,
                    'intern_id'            => $inc->rh_intern_id,
                    'student_name'         => $name,
                    'residence_hall'       => $inc->intern->room?->residence_hall,
                    'incident_type'        => $inc->incident_type,
                    'description'          => $inc->description,
                    'initial_intervention' => $inc->initial_intervention,
                    'referred_to'          => $inc->referred_to,
                    'follow_up_notes'      => $inc->follow_up_notes,
                    'status'               => $inc->status,
                    'logged_by_name'       => $inc->loggedBy?->name,
                    'created_at'           => $inc->created_at,
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('ResidenceHall/Incidents/Index', [
            'incidents' => $incidents,
            'filters'   => compact('type', 'status', 'search'),
            'myHall'    => $hall,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('rh.incidents.manage');

        $data = $request->validate([
            'rh_intern_id'         => 'required|exists:rh_interns,id',
            'incident_type'        => 'required|in:health,behavioral,psychological',
            'description'          => 'required|string',
            'initial_intervention' => 'nullable|string',
            'referred_to'          => 'nullable|string|max:255',
        ]);

        $intern = RhIntern::findOrFail($data['rh_intern_id']);
        $hall   = $this->hallScope();
        if ($hall && $intern->room && $intern->room->residence_hall !== $hall) abort(403);

        $data['logged_by'] = Auth::id();
        $data['status']    = 'open';
        RhIncident::create($data);

        return back()->with('success', 'Incident logged.');
    }

    public function update(Request $request, RhIncident $rhIncident)
    {
        $this->authorize('rh.incidents.manage');

        $data = $request->validate([
            'initial_intervention' => 'nullable|string',
            'referred_to'          => 'nullable|string|max:255',
            'follow_up_notes'      => 'nullable|string',
        ]);

        $rhIncident->update($data);
        return back()->with('success', 'Incident updated.');
    }

    public function resolve(RhIncident $rhIncident)
    {
        $this->authorize('rh.incidents.manage');
        $rhIncident->update(['status' => 'resolved']);
        return back()->with('success', 'Incident marked as resolved.');
    }
}
