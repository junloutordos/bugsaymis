<?php

namespace App\Http\Controllers\LnD;

use App\Enums\ApprovalStep;
use App\Http\Controllers\Controller;
use App\Models\IndividualDevelopmentPlan;
use App\Models\LearningProgram;
use App\Models\TrainingNeed;
use App\Models\User;
use App\Services\SnapshotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class IDPController extends Controller
{
    public function __construct(private SnapshotService $snapshots) {}
    // ── Index ──────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('lnd.view');

        $query = IndividualDevelopmentPlan::with([
            'employee:id,name',
            'supervisor:id,name',
            'learningProgram:id,title',
            'trainingNeed:id,competency_area',
        ]);

        if ($employeeId = $request->input('employee_id')) {
            $query->forEmployee((int) $employeeId);
        }

        if ($supervisorId = $request->input('supervisor_id')) {
            $query->forSupervisor((int) $supervisorId);
        }

        if ($year = $request->input('year')) {
            $query->forYear((int) $year);
        }

        if ($approval = $request->input('approval_status')) {
            $query->where('approval_status', $approval);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($intervention = $request->input('intervention_type')) {
            $query->byIntervention($intervention);
        }

        $idps = $query->orderByDesc('year')
            ->orderBy('timeline_start')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('LnD/IDP/Index', [
            'idps'      => $idps,
            'employees' => User::orderBy('name')->get(['id', 'name']),
            'programs'  => LearningProgram::active()->orderBy('title')->get(['id', 'title']),
            'filters'   => $request->only([
                'employee_id', 'supervisor_id', 'year',
                'approval_status', 'status', 'intervention_type',
            ]),
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorize('lnd.create');

        $validated = $this->validateIdp($request);

        $validated['approval_status'] = 'draft';

        $idp = IndividualDevelopmentPlan::create($validated);

        return back()->with('success', "IDP for '{$idp->competency}' created.");
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(IndividualDevelopmentPlan $iDP)
    {
        $this->authorize('lnd.view');

        $iDP->load([
            'employee:id,name,email',
            'supervisor:id,name',
            'approvedBy:id,name',
            'trainingNeed',
            'learningProgram:id,title,type,provider,hours',
        ]);

        return Inertia::render('LnD/IDP/Show', [
            'idp' => $iDP,
        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function update(Request $request, IndividualDevelopmentPlan $iDP)
    {
        $this->authorize('lnd.edit');

        if (in_array($iDP->approval_status, ['approved'])) {
            return back()->withErrors(['error' => 'Cannot edit an approved IDP.']);
        }

        $validated = $this->validateIdp($request);

        $iDP->update($validated);

        return back()->with('success', 'IDP updated.');
    }

    // ── Submit for approval ────────────────────────────────────────────────────

    public function submit(IndividualDevelopmentPlan $iDP)
    {
        $this->authorize('lnd.create');

        if ($iDP->approval_status !== 'draft' && $iDP->approval_status !== 'returned') {
            return back()->withErrors(['error' => 'Only draft or returned IDPs can be submitted.']);
        }

        $iDP->update(['approval_status' => 'submitted']);

        return back()->with('success', 'IDP submitted for approval.');
    }

    // ── Approve / Return ───────────────────────────────────────────────────────

    public function approve(Request $request, IndividualDevelopmentPlan $iDP)
    {
        $this->authorize('lnd.approve');

        $validated = $request->validate([
            'action'             => ['required', Rule::in(['approved', 'returned'])],
            'supervisor_remarks' => ['nullable', 'string'],
        ]);

        $iDP->update([
            'approval_status'    => $validated['action'],
            'approved_by'        => Auth::id(),
            'approved_at'        => $validated['action'] === 'approved' ? now() : null,
            'supervisor_remarks' => $validated['supervisor_remarks'] ?? $iDP->supervisor_remarks,
        ]);

        $snapshotAction = $validated['action'] === 'approved' ? 'approved' : 'rejected';
        $this->snapshots->recordApproval(
            approvable: $iDP,
            step:       ApprovalStep::IDP_APPROVER,
            sequence:   1,
            action:     $snapshotAction,
            approver:   Auth::user(),
            remarks:    $validated['supervisor_remarks'] ?? '',
        );

        $label = $validated['action'] === 'approved' ? 'approved' : 'returned for revision';

        return back()->with('success', "IDP {$label}.");
    }

    // ── Update progress status ─────────────────────────────────────────────────

    public function updateStatus(Request $request, IndividualDevelopmentPlan $iDP)
    {
        $this->authorize('lnd.edit');

        $validated = $request->validate([
            'status'           => ['required', Rule::in(['planned', 'ongoing', 'completed', 'deferred', 'cancelled'])],
            'employee_remarks' => ['nullable', 'string'],
        ]);

        $iDP->update($validated);

        // If IDP is completed and linked to a training need, mark it as addressed
        if ($validated['status'] === 'completed' && $iDP->training_need_id) {
            $iDP->trainingNeed?->update(['status' => 'addressed']);
        }

        return back()->with('success', "IDP status updated to {$validated['status']}.");
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function destroy(IndividualDevelopmentPlan $iDP)
    {
        $this->authorize('lnd.delete');

        if ($iDP->approval_status === 'approved') {
            return back()->withErrors(['error' => 'Cannot delete an approved IDP.']);
        }

        $iDP->delete();

        return back()->with('success', 'IDP deleted.');
    }

    // ── My IDP (employee self-view) ────────────────────────────────────────────

    public function myIdp(Request $request)
    {
        $this->authorize('lnd.view');

        $year = $request->input('year', now()->year);

        $idps = IndividualDevelopmentPlan::with([
                'supervisor:id,name',
                'learningProgram:id,title,type',
                'trainingNeed:id,competency_area',
            ])
            ->forEmployee(Auth::id())
            ->forYear((int) $year)
            ->orderBy('timeline_start')
            ->get();

        return Inertia::render('LnD/IDP/My', [
            'idps' => $idps,
            'year' => (int) $year,
        ]);
    }

    // ── Team IDP (supervisor view) ─────────────────────────────────────────────

    public function teamIdp(Request $request)
    {
        $this->authorize('lnd.view');

        $year = $request->input('year', now()->year);

        $idps = IndividualDevelopmentPlan::with([
                'employee:id,name',
                'learningProgram:id,title',
                'trainingNeed:id,competency_area',
            ])
            ->forSupervisor(Auth::id())
            ->forYear((int) $year)
            ->orderBy('approval_status')
            ->orderBy('timeline_start')
            ->get();

        return Inertia::render('LnD/IDP/Team', [
            'idps' => $idps,
            'year' => (int) $year,
        ]);
    }

    // ── Validation helper ──────────────────────────────────────────────────────

    private function validateIdp(Request $request): array
    {
        return $request->validate([
            'employee_id'          => ['required', 'exists:users,id'],
            'supervisor_id'        => ['required', 'exists:users,id'],
            'training_need_id'     => ['nullable', 'exists:training_needs,id'],
            'learning_program_id'  => ['nullable', 'exists:learning_programs,id'],
            'year'                 => ['required', 'integer', 'min:2000', 'max:2099'],
            'competency'           => ['required', 'string', 'max:255'],
            'current_level'        => ['required', Rule::in(['none', 'basic', 'intermediate', 'advanced'])],
            'target_level'         => ['required', Rule::in(['none', 'basic', 'intermediate', 'advanced'])],
            'development_activity' => ['required', 'string'],
            'intervention_type'    => ['required', Rule::in(['training', 'coaching', 'assignment', 'self_study', 'e_learning', 'other'])],
            'timeline_start'       => ['nullable', 'date'],
            'timeline_end'         => ['nullable', 'date', 'after_or_equal:timeline_start'],
            'status'               => ['required', Rule::in(['planned', 'ongoing', 'completed', 'deferred', 'cancelled'])],
            'employee_remarks'     => ['nullable', 'string'],
        ]);
    }
}
