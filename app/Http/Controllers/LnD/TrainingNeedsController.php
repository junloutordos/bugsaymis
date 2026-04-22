<?php

namespace App\Http\Controllers\LnD;

use App\Http\Controllers\Controller;
use App\Models\TrainingNeed;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TrainingNeedsController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('lnd.view');

        $query = TrainingNeed::with([
            'employee:id,name',
            'assessedBy:id,name',
            'individualDevelopmentPlan:id,training_need_id,status',
        ]);

        if ($search = $request->input('search')) {
            $query->where(fn ($q) =>
                $q->where('competency_area', 'like', "%{$search}%")
                  ->orWhere('competency_gap', 'like', "%{$search}%")
                  ->orWhere('recommended_training', 'like', "%{$search}%")
            );
        }

        if ($employeeId = $request->input('employee_id')) {
            $query->forEmployee((int) $employeeId);
        }

        if ($year = $request->input('year')) {
            $query->forYear((int) $year);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority_level', $priority);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($source = $request->input('source')) {
            $query->bySource($source);
        }

        $needs = $query->orderByDesc('year')
            ->orderByRaw("FIELD(priority_level,'high','medium','low')")
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('LnD/TrainingNeeds/Index', [
            'needs'     => $needs,
            'employees' => User::orderBy('name')->get(['id', 'name']),
            'filters'   => $request->only(['search', 'employee_id', 'year', 'priority', 'status', 'source']),
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorize('lnd.create');

        $validated = $request->validate([
            'employee_id'          => ['required', 'exists:users,id'],
            'competency_area'      => ['required', 'string', 'max:255'],
            'competency_gap'       => ['nullable', 'string'],
            'current_level'        => ['required', Rule::in(['none', 'basic', 'intermediate', 'advanced'])],
            'target_level'         => ['required', Rule::in(['none', 'basic', 'intermediate', 'advanced'])],
            'priority_level'       => ['required', Rule::in(['high', 'medium', 'low'])],
            'recommended_training' => ['nullable', 'string', 'max:255'],
            'source'               => ['required', Rule::in(['self', 'supervisor', 'hr', 'ipcr', 'spms'])],
            'year'                 => ['required', 'integer', 'min:2000', 'max:2099'],
            'remarks'              => ['nullable', 'string'],
        ]);

        $validated['assessed_by'] = Auth::id();
        $validated['status']      = 'pending';

        $need = TrainingNeed::create($validated);

        return back()->with('success', "Training need for '{$need->competency_area}' recorded.");
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function update(Request $request, TrainingNeed $trainingNeed)
    {
        $this->authorize('lnd.edit');

        $validated = $request->validate([
            'competency_area'      => ['required', 'string', 'max:255'],
            'competency_gap'       => ['nullable', 'string'],
            'current_level'        => ['required', Rule::in(['none', 'basic', 'intermediate', 'advanced'])],
            'target_level'         => ['required', Rule::in(['none', 'basic', 'intermediate', 'advanced'])],
            'priority_level'       => ['required', Rule::in(['high', 'medium', 'low'])],
            'recommended_training' => ['nullable', 'string', 'max:255'],
            'source'               => ['required', Rule::in(['self', 'supervisor', 'hr', 'ipcr', 'spms'])],
            'year'                 => ['required', 'integer', 'min:2000', 'max:2099'],
            'remarks'              => ['nullable', 'string'],
        ]);

        $trainingNeed->update($validated);

        return back()->with('success', 'Training need updated.');
    }

    // ── Approve / Update status ────────────────────────────────────────────────

    public function approve(Request $request, TrainingNeed $trainingNeed)
    {
        $this->authorize('lnd.approve');

        $validated = $request->validate([
            'status'  => ['required', Rule::in(['approved', 'deferred'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $trainingNeed->update([
            'status'      => $validated['status'],
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'remarks'     => $validated['remarks'] ?? $trainingNeed->remarks,
        ]);

        return back()->with('success', "Training need {$validated['status']}.");
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function destroy(TrainingNeed $trainingNeed)
    {
        $this->authorize('lnd.delete');

        if ($trainingNeed->has_idp) {
            return back()->withErrors(['error' => 'Cannot delete a training need that already has an IDP.']);
        }

        $trainingNeed->delete();

        return back()->with('success', 'Training need deleted.');
    }

    // ── Consolidated TNA view (HR) ─────────────────────────────────────────────

    public function consolidation(Request $request)
    {
        $this->authorize('lnd.view');

        $year = $request->input('year', now()->year);

        $data = TrainingNeed::with(['employee:id,name'])
            ->forYear((int) $year)
            ->unaddressed()
            ->orderByRaw("FIELD(priority_level,'high','medium','low')")
            ->get()
            ->groupBy('competency_area');

        return Inertia::render('LnD/TrainingNeeds/Consolidation', [
            'year'         => (int) $year,
            'consolidated' => $data,
        ]);
    }

    // ── Submit own TNA (employee self-service) ─────────────────────────────────

    public function submitOwn(Request $request)
    {
        $this->authorize('lnd.view');

        $validated = $request->validate([
            'competency_area'      => ['required', 'string', 'max:255'],
            'competency_gap'       => ['nullable', 'string'],
            'current_level'        => ['required', Rule::in(['none', 'basic', 'intermediate', 'advanced'])],
            'target_level'         => ['required', Rule::in(['none', 'basic', 'intermediate', 'advanced'])],
            'priority_level'       => ['required', Rule::in(['high', 'medium', 'low'])],
            'recommended_training' => ['nullable', 'string', 'max:255'],
            'year'                 => ['required', 'integer', 'min:2000', 'max:2099'],
        ]);

        $validated['employee_id']  = Auth::id();
        $validated['assessed_by']  = Auth::id();
        $validated['source']       = 'self';
        $validated['status']       = 'pending';

        TrainingNeed::create($validated);

        return back()->with('success', 'Training need submitted.');
    }
}
