<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\ResidenceHall\RhApplication;
use App\Models\ResidenceHall\RhIntern;
use App\Services\Discipline\StudentResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RhApplicationController extends Controller
{
    public function __construct(private StudentResolver $students) {}

    public function index(Request $request)
    {
        $this->authorize('rh.applications.view');

        $user   = $request->user();
        $hall   = $user->rh_hall;
        $syId   = $request->query('school_year_id');
        $status = $request->query('status', '');
        $search = trim($request->query('search', ''));

        $schoolYears = SchoolYear::orderByDesc('id')->get(['id', 'name', 'is_current']);
        $currentSy   = $schoolYears->firstWhere('is_current', true);
        $activeSyId  = $syId ?: $currentSy?->id;

        $applications = RhApplication::when($activeSyId, fn ($q) => $q->where('school_year_id', $activeSyId))
            ->when($hall, fn ($q) => $q->where('preferred_hall', $hall))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->get();

        // Batch-resolve student names
        $studentIds = $applications->pluck('student_id')->unique()->values();
        $studentMap = $this->studentNameMap($studentIds);

        $applications->transform(function ($a) use ($studentMap) {
            $a->student_name = $studentMap[$a->student_id]['name'] ?? ('Student #' . $a->student_id);
            $a->student_grade_section = $studentMap[$a->student_id]['grade_section'] ?? null;
            return $a;
        });

        // Client-side search on resolved names
        if ($search) {
            $lower = mb_strtolower($search);
            $applications = $applications->filter(
                fn ($a) => str_contains(mb_strtolower($a->student_name), $lower)
            )->values();
        }

        return Inertia::render('ResidenceHall/Applications/Index', [
            'applications' => $applications,
            'schoolYears'  => $schoolYears,
            'activeSyId'   => (int) $activeSyId,
            'filters'      => compact('status', 'search'),
            'myHall'       => $hall,
        ]);
    }

    public function show(RhApplication $rhApplication)
    {
        $this->authorize('rh.applications.view');

        $hall = auth()->user()->rh_hall;
        if ($hall && $rhApplication->preferred_hall !== $hall) {
            abort(403);
        }

        $student = $this->students->resolve($rhApplication->student_id);

        $rhApplication->load(['evaluatedBy:id,name', 'approvedBy:id,name']);

        // Check if an intern record already exists (already converted)
        $intern = RhIntern::where('student_id', $rhApplication->student_id)
            ->where('school_year_id', $rhApplication->school_year_id)
            ->first();

        return Inertia::render('ResidenceHall/Applications/Show', [
            'application' => $rhApplication,
            'student'     => $student,
            'existingIntern' => $intern ? ['id' => $intern->id, 'status' => $intern->status] : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('rh.applications.evaluate');

        $validated = $request->validate([
            'student_id'            => ['required', 'integer'],
            'school_year_id'        => ['required', 'integer'],
            'grade_level'           => ['nullable', 'string', 'max:10'],
            'scholarship_category'  => ['nullable', 'string', 'max:50'],
            'home_province'         => ['nullable', 'string', 'max:100'],
            'estimated_distance_km' => ['nullable', 'integer', 'min:0'],
            'preferred_hall'        => ['required', 'in:BRH,GRH'],
            'foster_parent_name'    => ['nullable', 'string', 'max:200'],
            'foster_parent_contact' => ['nullable', 'string', 'max:50'],
            'foster_parent_address' => ['nullable', 'string', 'max:255'],
        ]);

        // Prevent duplicate per student/SY
        $exists = RhApplication::where('student_id', $validated['student_id'])
            ->where('school_year_id', $validated['school_year_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'An application for this student already exists for the selected school year.');
        }

        RhApplication::create($validated);

        return back()->with('success', 'Application recorded.');
    }

    public function evaluate(Request $request, RhApplication $rhApplication)
    {
        $this->authorize('rh.applications.evaluate');

        $validated = $request->validate([
            'status'           => ['required', 'in:evaluated,rejected,waitlisted'],
            'evaluation_notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $rhApplication->update([
            'status'           => $validated['status'],
            'evaluation_notes' => $validated['evaluation_notes'] ?? null,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'evaluated_by'     => auth()->id(),
            'evaluated_at'     => now(),
        ]);

        return back()->with('success', 'Application evaluated.');
    }

    public function approve(Request $request, RhApplication $rhApplication)
    {
        $this->authorize('rh.applications.approve');

        $validated = $request->validate([
            'status'           => ['required', 'in:approved,rejected'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $rhApplication->update([
            'status'           => $validated['status'],
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
        ]);

        return back()->with('success', $validated['status'] === 'approved'
            ? 'Application approved. You can now assign a room for this intern.'
            : 'Application rejected.');
    }

    private function studentNameMap($studentIds): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('students')
            ->whereIn('id', $studentIds)
            ->get(['id', 'lastname', 'firstname', 'middlename'])
            ->keyBy('id')
            ->map(fn ($s) => [
                'name'          => trim($s->lastname . ', ' . $s->firstname),
                'grade_section' => null,
            ])
            ->toArray();
    }
}
