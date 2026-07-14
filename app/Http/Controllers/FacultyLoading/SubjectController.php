<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.subjects');

        $currentSy   = SchoolYear::where('is_current', true)->first();
        $schoolYearId = (int) $request->input('school_year_id', $currentSy?->id);

        $query = Subject::where('school_year_id', $schoolYearId);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('code', 'like', "%{$s}%")
                                       ->orWhere('name', 'like', "%{$s}%"));
        }

        if ($request->filled('grade_level') && $request->grade_level !== 'all') {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->filled('subject_type') && $request->subject_type !== 'all') {
            $query->where('subject_type', $request->subject_type);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $subjectList = $query->orderBy('grade_level')->orderBy('code')->get();

        // Terms for the "who teaches this" dropdown — within the selected school year
        $terms = AcademicTerm::where('school_year_id', $schoolYearId)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $currentTermId = AcademicTerm::where('school_year_id', $schoolYearId)
            ->where('is_current', true)
            ->value('id')
            ?? $terms->first()?->get('id');

        $termId = (int) $request->input('term_id', $currentTermId);

        $assignmentsBySubject = collect();
        if ($termId) {
            $assignmentsBySubject = LoadAssignment::with('faculty:id,name')
                ->where('assignment_type', 'teaching')
                ->where('academic_term_id', $termId)
                ->whereIn('subject_id', $subjectList->pluck('id'))
                ->get()
                ->groupBy('subject_id')
                ->map(fn ($rows) => $rows
                    ->unique('user_id')
                    ->map(fn ($a) => ['id' => $a->user_id, 'name' => $a->faculty?->name ?? '—'])
                    ->values()
                );
        }

        $subjects = $subjectList->map(fn ($s) => [
            'id'                  => $s->id,
            'code'                => $s->code,
            'name'                => $s->name,
            'description'         => $s->description,
            'specialization_tags' => $s->specialization_tags,
            'credit_units'        => $s->credit_units,
            'lecture_hours'       => $s->lecture_hours,
            'lab_hours'           => $s->lab_hours,
            'load_units'          => $s->load_units,
            'subject_type'        => $s->subject_type,
            'grade_level'         => $s->grade_level,
            'semester'            => $s->semester,
            'sessions_per_week'   => $s->sessions_per_week,
            'minutes_per_session' => $s->minutes_per_session,
            'is_active'           => $s->is_active,
            'has_ilp'             => $s->has_ilp,
            'faculty'             => $assignmentsBySubject->get($s->id, collect())->all(),
        ]);

        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);

        return Inertia::render('FacultyLoading/Subjects/Index', [
            'subjects'            => $subjects,
            'terms'               => $terms,
            'currentTermId'       => $termId,
            'schoolYears'         => $schoolYears,
            'currentSchoolYearId' => $schoolYearId,
            'filters'             => $request->only(['search', 'grade_level', 'subject_type', 'active', 'term_id', 'school_year_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.subjects');

        $schoolYearId = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id')
        );

        $data = $request->validate([
            'school_year_id'       => 'required|exists:school_years,id',
            'code'                 => "required|string|max:20|unique:subjects,code,NULL,id,school_year_id,{$schoolYearId}",
            'name'                 => 'required|string|max:150',
            'description'          => 'nullable|string',
            'specialization_tags'  => 'nullable|string|max:500',
            'credit_units'         => 'required|integer|min:0',
            'lecture_hours'        => 'required|numeric|min:0',
            'lab_hours'            => 'nullable|numeric|min:0',
            'load_units'           => 'required|numeric|min:0',
            'subject_type'         => 'required|in:lecture,laboratory,lecture_lab,research,special',
            'grade_level'          => 'required|integer|min:0|max:12',
            'semester'             => 'nullable|in:1st,2nd,both',
            'sessions_per_week'    => 'required|integer|min:1',
            'minutes_per_session'  => 'required|integer|min:1',
            'is_active'            => 'boolean',
            'has_ilp'              => 'boolean',
        ]);

        Subject::create($data);

        return back()->with('success', 'Subject created.');
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorize('faculty_loading.subjects');

        $schoolYearId = $subject->school_year_id;

        $data = $request->validate([
            'code'                 => "required|string|max:20|unique:subjects,code,{$subject->id},id,school_year_id,{$schoolYearId}",
            'name'                 => 'required|string|max:150',
            'description'          => 'nullable|string',
            'specialization_tags'  => 'nullable|string|max:500',
            'credit_units'         => 'required|integer|min:0',
            'lecture_hours'        => 'required|numeric|min:0',
            'lab_hours'            => 'nullable|numeric|min:0',
            'load_units'           => 'required|numeric|min:0',
            'subject_type'         => 'required|in:lecture,laboratory,lecture_lab,research,special',
            'grade_level'          => 'required|integer|min:0|max:12',
            'semester'             => 'nullable|in:1st,2nd,both',
            'sessions_per_week'    => 'required|integer|min:1',
            'minutes_per_session'  => 'required|integer|min:1',
            'is_active'            => 'boolean',
            'has_ilp'              => 'boolean',
        ]);

        $subject->update($data);

        return back()->with('success', 'Subject updated.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $this->authorize('faculty_loading.subjects');

        if ($subject->loadAssignments()->exists() || $subject->classSchedules()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete: subject has load assignments or schedules.']);
        }

        $subject->delete();

        return back()->with('success', 'Subject deleted.');
    }

    /**
     * Copy all subjects from a source school year into a target school year.
     * Skips any subject code that already exists in the target year.
     */
    public function copyFromYear(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.subjects');

        $data = $request->validate([
            'source_school_year_id' => 'required|exists:school_years,id',
            'target_school_year_id' => 'required|exists:school_years,id|different:source_school_year_id',
        ]);

        $sourceId = $data['source_school_year_id'];
        $targetId = $data['target_school_year_id'];

        $existing = Subject::where('school_year_id', $targetId)->pluck('code')->flip();

        $sources = Subject::where('school_year_id', $sourceId)->get();

        $copied = 0;
        foreach ($sources as $s) {
            if ($existing->has($s->code)) {
                continue;
            }
            Subject::create([
                'school_year_id'      => $targetId,
                'code'                => $s->code,
                'name'                => $s->name,
                'description'         => $s->description,
                'specialization_tags' => $s->specialization_tags,
                'credit_units'        => $s->credit_units,
                'lecture_hours'       => $s->lecture_hours,
                'lab_hours'           => $s->lab_hours,
                'load_units'          => $s->load_units,
                'subject_type'        => $s->subject_type,
                'grade_level'         => $s->grade_level,
                'semester'            => $s->semester,
                'sessions_per_week'   => $s->sessions_per_week,
                'minutes_per_session' => $s->minutes_per_session,
                'academic_unit_id'    => $s->academic_unit_id,
                'is_active'           => $s->is_active,
                'has_ilp'             => $s->has_ilp,
            ]);
            $copied++;
        }

        $skipped = $sources->count() - $copied;
        $msg = "{$copied} subject(s) copied.";
        if ($skipped > 0) {
            $msg .= " {$skipped} skipped (codes already exist in target year).";
        }

        return back()->with('success', $msg);
    }
}
