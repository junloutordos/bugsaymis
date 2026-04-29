<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\FacultyVacancy;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\FacultyLoading\FacultyVacancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FacultyVacancyController extends Controller
{
    public function __construct(private readonly FacultyVacancyService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.vacancies');

        $schoolYears   = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);
        $academicUnits = AcademicUnit::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'code']);

        $selectedYear = $request->input('school_year_id',
            $schoolYears->firstWhere('is_current', true)?->id ?? $schoolYears->first()?->id
        );

        $vacancies = FacultyVacancy::with([
                'academicUnit', 'sstPosition', 'subject', 'academicTerm', 'filledBy',
            ])
            ->where('school_year_id', $selectedYear)
            ->when($request->filled('academic_unit_id'), fn ($q) => $q->where('academic_unit_id', $request->academic_unit_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('academic_unit_id')
            ->orderBy('status')
            ->get()
            ->map(fn ($v) => [
                'id'               => $v->id,
                'academic_unit'    => ['id' => $v->academicUnit?->id, 'name' => $v->academicUnit?->name],
                'academic_term'    => $v->academicTerm ? ['id' => $v->academicTerm->id, 'name' => $v->academicTerm->name] : null,
                'sst_position'     => $v->sstPosition  ? ['id' => $v->sstPosition->id,  'code' => $v->sstPosition->code]  : null,
                'subject'          => $v->subject       ? ['id' => $v->subject->id,      'name' => $v->subject->name]      : null,
                'status'           => $v->status,
                'notes'            => $v->notes,
                'filled_by'        => $v->filledBy ? ['id' => $v->filledBy->id, 'name' => $v->filledBy->name] : null,
                'filled_at'        => $v->filled_at?->toDateString(),
            ]);

        $openCounts = $this->service->openCountByUnit($selectedYear);

        return Inertia::render('FacultyLoading/Vacancies/Index', [
            'vacancies'       => $vacancies,
            'schoolYears'     => $schoolYears,
            'academicUnits'   => $academicUnits,
            'openCounts'      => $openCounts,
            'selectedYearId'  => (int) $selectedYear,
            'filters'         => $request->only(['academic_unit_id', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.vacancies');

        $request->validate([
            'school_year_id'   => 'required|exists:school_years,id',
            'academic_unit_id' => 'required|exists:academic_units,id',
            'academic_term_id' => 'nullable|exists:academic_terms,id',
            'sst_position_id'  => 'nullable|exists:sst_positions,id',
            'subject_id'       => 'nullable|exists:subjects,id',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $result = $this->service->open($request->all(), $request->user()->id);

        if (! $result['ok']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', 'Vacancy opened.');
    }

    public function fill(Request $request, FacultyVacancy $vacancy): RedirectResponse
    {
        $this->authorize('faculty_loading.vacancies');

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $faculty = User::findOrFail($data['user_id']);
        $result  = $this->service->fill($vacancy, $faculty);

        if (! $result['ok']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function cancel(FacultyVacancy $vacancy): RedirectResponse
    {
        $this->authorize('faculty_loading.vacancies');

        $result = $this->service->cancel($vacancy);

        if (! $result['ok']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function destroy(FacultyVacancy $vacancy): RedirectResponse
    {
        $this->authorize('faculty_loading.vacancies');

        if ($vacancy->status === 'filled') {
            return back()->withErrors(['error' => 'Cannot delete a filled vacancy.']);
        }

        $vacancy->delete();

        return back()->with('success', 'Vacancy deleted.');
    }
}
