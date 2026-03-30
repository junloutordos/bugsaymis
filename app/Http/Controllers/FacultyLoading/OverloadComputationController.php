<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\OverloadComputation;
use App\Models\FacultyLoading\SalarySchedule;
use App\Models\FacultyLoading\SstPosition;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OverloadComputationController extends Controller
{
    public function __construct(private readonly LoadComputationService $loads) {}

    // ── List overload computations ────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.approve');

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        $computations = OverloadComputation::with(['faculty:id,name,sst_position_id', 'faculty.sstPosition', 'facultyLoad', 'academicTerm.schoolYear'])
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => $this->mapComputation($c));

        // Overloaded faculty in this term without a computation yet
        $pendingLoads = FacultyLoad::with(['faculty:id,name,sst_position_id', 'faculty.sstPosition'])
            ->where('load_status', 'overload')
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->whereNotIn('id', OverloadComputation::whereIn('status', ['pending', 'for_approval', 'approved', 'paid'])
                ->pluck('faculty_load_id'))
            ->get()
            ->map(fn ($l) => [
                'id'             => $l->id,
                'overload_units' => $l->overload_units,
                'faculty'        => $l->faculty ? [
                    'id'           => $l->faculty->id,
                    'name'         => $l->faculty->name,
                    'salary_grade' => $l->faculty->sstPosition?->salary_grade,
                    'position'     => $l->faculty->sstPosition?->code,
                ] : null,
            ]);

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $positions = SstPosition::active()->orderBy('salary_grade')
            ->get(['id', 'code', 'salary_grade']);

        return Inertia::render('FacultyLoading/OverloadComputations/Index', [
            'computations' => $computations,
            'pendingLoads' => $pendingLoads,
            'terms'        => $terms,
            'positions'    => $positions,
            'currentTerm'  => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'      => $request->only(['term_id']),
        ]);
    }

    // ── Preview PHTR and overload pay (JSON — no DB write) ────────────────────

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.approve');

        $data = $request->validate([
            'annual_rate'    => 'required|numeric|min:1',
            'overload_units' => 'required|numeric|min:0',
            'overload_hours' => 'required|numeric|min:0',
            'term_weeks'     => 'nullable|integer|min:1|max:26',
        ]);

        $breakdown = $this->loads->computeOverloadBreakdown(
            (float) $data['annual_rate'],
            (float) $data['overload_units'],
            (float) $data['overload_hours'],
            (int)   ($data['term_weeks'] ?? 18)
        );

        return response()->json($breakdown);
    }

    // ── Create / submit an overload computation for approval ──────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        $data = $request->validate([
            'faculty_load_id' => 'required|exists:faculty_loads,id',
            'annual_rate'     => 'required|numeric|min:1',
            'overload_hours'  => 'required|numeric|min:0.5',
            'term_weeks'      => 'nullable|integer|min:1|max:26',
            'remarks'         => 'nullable|string|max:500',
        ]);

        $load = FacultyLoad::findOrFail($data['faculty_load_id']);

        if (! $load->isOverload()) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record does not have an overload.']);
        }

        $existing = OverloadComputation::where('faculty_load_id', $load->id)
            ->whereIn('status', ['pending', 'for_approval', 'approved'])
            ->exists();

        if ($existing) {
            return back()->withErrors(['faculty_load_id' => 'An overload computation already exists for this load record.']);
        }

        $breakdown = $this->loads->computeOverloadBreakdown(
            (float) $data['annual_rate'],
            $load->overload_units,
            (float) $data['overload_hours'],
            (int) ($data['term_weeks'] ?? 18)
        );

        OverloadComputation::create([
            'user_id'            => $load->user_id,
            'faculty_load_id'    => $load->id,
            'school_year_id'     => $load->school_year_id,
            'academic_term_id'   => $load->academic_term_id,
            'annual_rate'        => $breakdown['annual_rate'],
            'overload_units'     => $breakdown['overload_units'],
            'overload_hours'     => $breakdown['overload_hours'],
            'phtr'               => $breakdown['phtr'],
            'total_overload_pay' => $breakdown['total_overload_pay'],
            'term_weeks'         => $breakdown['term_weeks'],
            'status'             => 'for_approval',
            'requested_by'       => Auth::id(),
            'remarks'            => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Overload computation submitted for approval.');
    }

    // ── Bulk generate computations for all overloaded faculty in a term ────────

    public function bulkCompute(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        $data = $request->validate([
            'academic_term_id' => 'required|exists:academic_terms,id',
            'overload_hours'   => 'required|numeric|min:0.5',
            'term_weeks'       => 'nullable|integer|min:1|max:26',
            'step'             => 'nullable|integer|min:1|max:8',
        ]);

        $termId    = $data['academic_term_id'];
        $hours     = (float) $data['overload_hours'];
        $weeks     = (int) ($data['term_weeks'] ?? 18);
        $step      = (int) ($data['step'] ?? 1);
        $requestor = Auth::id();

        // Get all overloaded loads in this term without a computation
        $loads = FacultyLoad::with(['faculty.sstPosition'])
            ->where('academic_term_id', $termId)
            ->where('load_status', 'overload')
            ->whereNotIn('id', OverloadComputation::whereIn('status', ['pending', 'for_approval', 'approved', 'paid'])
                ->pluck('faculty_load_id'))
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($loads as $load) {
            $salaryGrade = $load->faculty?->sstPosition?->salary_grade;
            $annualRate  = $salaryGrade
                ? SalarySchedule::lookupAnnualRate($salaryGrade, $step)
                : null;

            if (! $annualRate) {
                $skipped++;
                continue;
            }

            $breakdown = $this->loads->computeOverloadBreakdown(
                $annualRate,
                $load->overload_units,
                $hours,
                $weeks
            );

            OverloadComputation::create([
                'user_id'            => $load->user_id,
                'faculty_load_id'    => $load->id,
                'school_year_id'     => $load->school_year_id,
                'academic_term_id'   => $load->academic_term_id,
                'annual_rate'        => $breakdown['annual_rate'],
                'overload_units'     => $breakdown['overload_units'],
                'overload_hours'     => $breakdown['overload_hours'],
                'phtr'               => $breakdown['phtr'],
                'total_overload_pay' => $breakdown['total_overload_pay'],
                'term_weeks'         => $breakdown['term_weeks'],
                'status'             => 'for_approval',
                'requested_by'       => $requestor,
            ]);

            $created++;
        }

        $msg = "Bulk computation: {$created} created.";
        if ($skipped > 0) {
            $msg .= " {$skipped} skipped (no salary grade / schedule not found).";
        }

        return back()->with('success', $msg);
    }

    // ── Approve or reject an overload computation ─────────────────────────────

    public function approve(Request $request, OverloadComputation $overloadComputation): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        $data = $request->validate([
            'approved' => 'required|boolean',
            'remarks'  => 'nullable|string|max:500',
        ]);

        if (! $overloadComputation->isPending() && $overloadComputation->status !== 'for_approval') {
            return back()->withErrors(['status' => 'Only pending computations can be approved or rejected.']);
        }

        $newStatus = $data['approved'] ? 'approved' : 'rejected';

        $overloadComputation->update([
            'status'      => $newStatus,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'remarks'     => $data['remarks'] ?? $overloadComputation->remarks,
        ]);

        if ($data['approved'] && $overloadComputation->facultyLoad) {
            $overloadComputation->facultyLoad->update([
                'overload_approved' => true,
                'approved_by'       => Auth::id(),
                'approved_at'       => now(),
                'approval_remarks'  => $data['remarks'] ?? null,
            ]);
        }

        $action = $data['approved'] ? 'approved' : 'rejected';
        return back()->with('success', "Overload computation {$action}.");
    }

    // ── Mark computation as paid ──────────────────────────────────────────────

    public function markPaid(OverloadComputation $overloadComputation): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        if (! $overloadComputation->isApproved()) {
            return back()->withErrors(['status' => 'Only approved computations can be marked as paid.']);
        }

        $overloadComputation->update(['status' => 'paid']);

        // Lock the linked FacultyLoad to prevent retrospective edits
        if ($overloadComputation->facultyLoad) {
            $overloadComputation->facultyLoad->update(['is_locked' => true]);
        }

        return back()->with('success', 'Overload payment recorded.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function mapComputation(OverloadComputation $c): array
    {
        $sg = $c->faculty?->sstPosition?->salary_grade;

        return [
            'id'               => $c->id,
            'annual_rate'      => (float) $c->annual_rate,
            'overload_units'   => (float) $c->overload_units,
            'overload_hours'   => (float) $c->overload_hours,
            'phtr'             => (float) $c->phtr,
            'total_overload_pay' => (float) $c->total_overload_pay,
            'term_weeks'       => $c->term_weeks,
            'status'           => $c->status,
            'remarks'          => $c->remarks,
            'approved_at'      => $c->approved_at?->toDateTimeString(),
            'faculty'          => $c->faculty ? [
                'id'           => $c->faculty->id,
                'name'         => $c->faculty->name,
                'salary_grade' => $sg,
                'position'     => $c->faculty->sstPosition?->code,
            ] : null,
            'term'         => $c->academicTerm ? ['id' => $c->academicTerm->id, 'label' => $c->academicTerm->full_label] : null,
            'faculty_load' => $c->facultyLoad ? [
                'id'             => $c->facultyLoad->id,
                'total_units'    => (float) $c->facultyLoad->total_units,
                'overload_units' => $c->facultyLoad->overload_units,
                'load_status'    => $c->facultyLoad->load_status,
            ] : null,
        ];
    }
}
