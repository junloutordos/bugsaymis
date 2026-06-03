<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\EnrollmentPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('students.enrollment.manage');

        $currentId    = SchoolYear::where('is_current', true)->value('id');
        $schoolYearId = (int) $request->input('school_year_id', $currentId);

        $periods = EnrollmentPeriod::where('school_year_id', $schoolYearId)
            ->with('createdBy:id,name')
            ->orderBy('grade_level')
            ->orderBy('open_at')
            ->get()
            ->map(fn ($p) => [
                'id'                  => $p->id,
                'school_year_id'      => $p->school_year_id,
                'grade_level'         => $p->grade_level,
                'label'               => $p->label,
                'open_at'             => $p->open_at?->toIso8601String(),
                'close_at'            => $p->close_at?->toIso8601String(),
                'is_manually_open'    => $p->is_manually_open,
                'is_manually_closed'  => $p->is_manually_closed,
                'is_open'             => $p->isOpen(),
                'created_by_name'     => $p->createdBy?->name,
            ]);

        $schoolYears = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        return Inertia::render('Registrar/Enrollment/Periods', [
            'periods'            => $periods,
            'schoolYears'        => $schoolYears,
            'selectedSchoolYear' => $schoolYearId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'grade_level'    => ['nullable', 'integer', Rule::in([null, 7, 8, 9, 10, 11, 12])],
            'label'          => ['required', 'string', 'max:100'],
            'open_at'        => ['required', 'date'],
            'close_at'       => ['required', 'date', 'after:open_at'],
        ]);

        EnrollmentPeriod::create(array_merge($data, ['created_by' => Auth::id()]));

        return back()->with('success', 'Enrollment period created.');
    }

    public function update(Request $request, EnrollmentPeriod $period): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $data = $request->validate([
            'label'    => ['required', 'string', 'max:100'],
            'open_at'  => ['required', 'date'],
            'close_at' => ['required', 'date', 'after:open_at'],
        ]);

        $period->update($data);

        return back()->with('success', 'Enrollment period updated.');
    }

    public function toggle(Request $request, EnrollmentPeriod $period): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $action = $request->validate([
            'action' => ['required', Rule::in(['open', 'close', 'auto'])],
        ])['action'];

        $period->update([
            'is_manually_open'   => $action === 'open',
            'is_manually_closed' => $action === 'close',
        ]);

        $label = match ($action) {
            'open'  => 'Enrollment period force-opened.',
            'close' => 'Enrollment period force-closed.',
            default => 'Enrollment period set to automatic (date-based).',
        };

        return back()->with('success', $label);
    }

    public function destroy(EnrollmentPeriod $period): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        $period->delete();

        return back()->with('success', 'Enrollment period deleted.');
    }
}
