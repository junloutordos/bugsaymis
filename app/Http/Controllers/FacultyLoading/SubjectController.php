<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
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

        $query = Subject::query();

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

        $subjects = $query->orderBy('grade_level')->orderBy('code')->get()->map(fn ($s) => [
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
        ]);

        return Inertia::render('FacultyLoading/Subjects/Index', [
            'subjects' => $subjects,
            'filters'  => $request->only(['search', 'grade_level', 'subject_type', 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.subjects');

        $data = $request->validate([
            'code'                 => 'required|string|max:20|unique:subjects,code',
            'name'                 => 'required|string|max:150',
            'description'          => 'nullable|string',
            'specialization_tags'  => 'nullable|string|max:500',
            'credit_units'         => 'required|integer|min:0',
            'lecture_hours'       => 'required|numeric|min:0',
            'lab_hours'           => 'nullable|numeric|min:0',
            'load_units'          => 'required|numeric|min:0',
            'subject_type'        => 'required|in:lecture,laboratory,lecture_lab,research,special',
            'grade_level'         => 'required|integer|min:0|max:12',
            'semester'            => 'nullable|in:1st,2nd,both',
            'sessions_per_week'   => 'required|integer|min:1',
            'minutes_per_session' => 'required|integer|min:1',
            'is_active'           => 'boolean',
        ]);

        Subject::create($data);

        return back()->with('success', 'Subject created.');
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $this->authorize('faculty_loading.subjects');

        $data = $request->validate([
            'code'                 => "required|string|max:20|unique:subjects,code,{$subject->id}",
            'name'                 => 'required|string|max:150',
            'description'          => 'nullable|string',
            'specialization_tags'  => 'nullable|string|max:500',
            'credit_units'         => 'required|integer|min:0',
            'lecture_hours'       => 'required|numeric|min:0',
            'lab_hours'           => 'nullable|numeric|min:0',
            'load_units'          => 'required|numeric|min:0',
            'subject_type'        => 'required|in:lecture,laboratory,lecture_lab,research,special',
            'grade_level'         => 'required|integer|min:0|max:12',
            'semester'            => 'nullable|in:1st,2nd,both',
            'sessions_per_week'   => 'required|integer|min:1',
            'minutes_per_session' => 'required|integer|min:1',
            'is_active'           => 'boolean',
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
}
