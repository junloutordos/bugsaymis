<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\FacultyLoading\SchoolYearWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolYearController extends Controller
{
    public function __construct(private readonly SchoolYearWorkflowService $workflow) {}
    public function index(): Response
    {
        $this->authorize('faculty_loading.school_year');

        $schoolYears = SchoolYear::with('terms')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($sy) => [
                'id'              => $sy->id,
                'name'            => $sy->name,
                'start_date'      => $sy->start_date?->toDateString(),
                'end_date'        => $sy->end_date?->toDateString(),
                'is_current'      => $sy->is_current,
                'status'          => $sy->status,
                'workflow_status' => $sy->workflow_status,
                'terms'           => $sy->terms->map(fn ($t) => [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'term_type'  => $t->term_type,
                    'start_date' => $t->start_date?->toDateString(),
                    'end_date'   => $t->end_date?->toDateString(),
                    'is_current' => $t->is_current,
                ]),
            ]);

        return Inertia::render('FacultyLoading/SchoolYears/Index', [
            'schoolYears' => $schoolYears,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.school_year');

        $data = $request->validate([
            'name'       => 'required|string|max:20|unique:school_years,name',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'boolean',
            'status'     => 'required|in:active,inactive,archived',
        ]);

        if (! empty($data['is_current'])) {
            SchoolYear::query()->update(['is_current' => false]);
        }

        SchoolYear::create($data);

        return back()->with('success', 'School year created.');
    }

    public function update(Request $request, SchoolYear $schoolYear): RedirectResponse
    {
        $this->authorize('faculty_loading.school_year');

        $data = $request->validate([
            'name'       => "required|string|max:20|unique:school_years,name,{$schoolYear->id}",
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'boolean',
            'status'     => 'required|in:active,inactive,archived',
        ]);

        if (! empty($data['is_current'])) {
            SchoolYear::where('id', '!=', $schoolYear->id)->update(['is_current' => false]);
        }

        $schoolYear->update($data);

        return back()->with('success', 'School year updated.');
    }

    public function destroy(SchoolYear $schoolYear): RedirectResponse
    {
        $this->authorize('faculty_loading.school_year');

        if ($schoolYear->facultyLoads()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete: school year has faculty load records.']);
        }

        $schoolYear->delete();

        return back()->with('success', 'School year deleted.');
    }

    // ── Workflow ──────────────────────────────────────────────────────────────

    public function advance(SchoolYear $schoolYear): RedirectResponse
    {
        $this->authorize('faculty_loading.school_year');

        $result = $this->workflow->advance($schoolYear);

        if (! $result['ok']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function archive(SchoolYear $schoolYear): RedirectResponse
    {
        $this->authorize('faculty_loading.school_year');

        $result = $this->workflow->archive($schoolYear);

        if (! $result['ok']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    // ── Academic Terms ────────────────────────────────────────────────────────

    public function storeTerm(Request $request, SchoolYear $schoolYear): RedirectResponse
    {
        $this->authorize('faculty_loading.school_year');

        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'term_type'  => 'required|in:1st_semester,2nd_semester,summer,trimester,quarter,full_term',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]);

        if (! empty($data['is_current'])) {
            AcademicTerm::query()->update(['is_current' => false]);
        }

        $schoolYear->terms()->create($data);

        return back()->with('success', 'Academic term created.');
    }

    public function updateTerm(Request $request, AcademicTerm $term): RedirectResponse
    {
        $this->authorize('faculty_loading.school_year');

        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'term_type'  => 'required|in:1st_semester,2nd_semester,summer,trimester,quarter,full_term',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]);

        if (! empty($data['is_current'])) {
            AcademicTerm::where('id', '!=', $term->id)->update(['is_current' => false]);
        }

        $term->update($data);

        return back()->with('success', 'Term updated.');
    }

    public function destroyTerm(AcademicTerm $term): RedirectResponse
    {
        $this->authorize('faculty_loading.school_year');

        if ($term->classSchedules()->exists() || $term->facultyLoads()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete: term has associated records.']);
        }

        $term->delete();

        return back()->with('success', 'Term deleted.');
    }
}
