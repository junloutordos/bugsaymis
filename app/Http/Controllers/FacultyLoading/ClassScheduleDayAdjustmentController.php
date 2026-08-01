<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\User;
use App\Services\FacultyLoading\AdjustedClassScheduleService;
use App\Services\PersonNameFormatter;
use App\Services\SchoolCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClassScheduleDayAdjustmentController extends Controller
{
    private const GRADES = [7, 8, 9, 10, 11, 12];

    public function __construct(
        private readonly SchoolCalendarService $calendar,
        private readonly AdjustedClassScheduleService $adjustedSchedules,
        private readonly PersonNameFormatter $names,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        $termId = $request->integer('term_id') ?: AcademicTerm::where('is_current', true)->value('id');
        $term = $termId ? AcademicTerm::with('schoolYear')->findOrFail($termId) : null;

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()->map(fn ($item) => [
            'id' => $item->id,
            'label' => $item->full_label,
            'start_date' => $item->start_date?->toDateString(),
            'end_date' => $item->end_date?->toDateString(),
            'is_current' => (bool) $item->is_current,
        ]);

        $adjustments = ClassScheduleDayAdjustment::with(['createdBy:id,name', 'publishedBy:id,name', 'cancelledBy:id,name'])
            ->when($termId, fn ($query) => $query->where('academic_term_id', $termId))
            ->orderByDesc('effective_date')
            ->get()
            ->map(fn ($adjustment) => [
                'id' => $adjustment->id,
                'postponed_from_date' => $adjustment->postponed_from_date->toDateString(),
                'effective_date' => $adjustment->effective_date->toDateString(),
                'weekday' => $adjustment->effective_date->englishDayOfWeek,
                'reason' => $adjustment->reason,
                'shift_minutes' => $adjustment->shift_minutes,
                'status' => $adjustment->status,
                'created_by' => $adjustment->createdBy?->name,
                'published_by' => $adjustment->publishedBy?->name,
                'published_at' => $adjustment->published_at?->toIso8601String(),
                'cancelled_by' => $adjustment->cancelledBy?->name,
                'cancelled_at' => $adjustment->cancelled_at?->toIso8601String(),
            ]);

        return Inertia::render('FacultyLoading/Schedules/DayAdjustments', [
            'term' => $term ? [
                'id' => $term->id,
                'label' => $term->full_label,
                'start_date' => $term->start_date?->toDateString(),
                'end_date' => $term->end_date?->toDateString(),
            ] : null,
            'terms' => $terms,
            'adjustments' => $adjustments,
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'postponed_from_date' => ['required', 'date'],
        ]);

        $term = AcademicTerm::findOrFail($data['academic_term_id']);
        $this->validateAffectedMonday($data['postponed_from_date'], $term);

        return response()->json([
            'effective_date' => $this->calendar->nextSchoolDayAfter(
                $data['postponed_from_date'],
                self::GRADES,
                $term->end_date?->toDateString(),
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $this->validatedData($request);

        ClassScheduleDayAdjustment::create([
            ...$data,
            'adjustment_type' => 'flag_ceremony',
            'ceremony_start_time' => '07:30',
            'ceremony_end_time' => '08:00',
            'shift_minutes' => 30,
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Adjusted-day schedule saved as a draft.');
    }

    public function update(Request $request, ClassScheduleDayAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be edited.');

        $adjustment->update($this->validatedData($request, $adjustment));

        return back()->with('success', 'Adjusted-day draft updated.');
    }

    public function publish(ClassScheduleDayAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be published.');

        DB::transaction(function () use ($adjustment) {
            $locked = ClassScheduleDayAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'draft', 422, 'This adjustment is no longer a draft.');

            $locked->update([
                'schedule_snapshot' => $this->adjustedSchedules->generate($locked),
                'status' => 'published',
                'published_by' => Auth::id(),
                'published_at' => now(),
            ]);
        });

        return back()->with('success', 'Adjusted-day schedule published and frozen for official printing.');
    }

    public function cancel(ClassScheduleDayAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status === 'cancelled', 422, 'This adjustment is already cancelled.');

        $adjustment->update([
            'status' => 'cancelled',
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
        ]);

        return back()->with('success', 'Adjusted-day schedule cancelled. The regular schedule will apply.');
    }

    public function print(Request $request, ClassScheduleDayAdjustment $adjustment): Response
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status === 'cancelled', 404);

        $adjustment->loadMissing(['academicTerm.schoolYear', 'publishedBy:id,name', 'createdBy:id,name']);
        $snapshot = $this->adjustedSchedules->printableSnapshot($adjustment);
        $grade = $request->integer('grade');

        if ($grade) {
            abort_unless(in_array($grade, self::GRADES, true), 404);
            $snapshot['grades'] = array_values(array_filter(
                $snapshot['grades'],
                fn (array $item) => (int) $item['grade_level'] === $grade,
            ));
        }

        $director = User::where('position', 'like', '%Director%')
            ->where('position', 'not like', '%Assistant%')
            ->where('status', '<>', 'inactive')
            ->first(['id', 'name', 'position']);
        $prepared = $adjustment->publishedBy ?? $adjustment->createdBy;

        return Inertia::render('FacultyLoading/Schedules/PrintAdjustedDay', [
            'adjustment' => [
                'id' => $adjustment->id,
                'postponed_from_date' => $adjustment->postponed_from_date->toDateString(),
                'effective_date' => $adjustment->effective_date->toDateString(),
                'reason' => $adjustment->reason,
                'status' => $adjustment->status,
                'shift_minutes' => $adjustment->shift_minutes,
            ],
            'term' => [
                'label' => $adjustment->academicTerm->full_label,
                'school_year' => $adjustment->academicTerm->schoolYear?->name,
            ],
            'snapshot' => $snapshot,
            'signatories' => [
                'prepared' => $prepared ? [
                    'name' => $this->names->formal($prepared),
                    'position' => $prepared->position ?? 'Chief, Curriculum and Instruction Division',
                ] : null,
                'approved' => $director ? [
                    'name' => $this->names->formal($director),
                    'position' => $director->position ?? 'Campus Director',
                ] : null,
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function validatedData(Request $request, ?ClassScheduleDayAdjustment $adjustment = null): array
    {
        $data = $request->validate([
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'postponed_from_date' => ['required', 'date'],
            'effective_date' => ['required', 'date', 'after:postponed_from_date'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $term = AcademicTerm::findOrFail($data['academic_term_id']);
        $this->validateAffectedMonday($data['postponed_from_date'], $term);

        $expectedDate = $this->calendar->nextSchoolDayAfter(
            $data['postponed_from_date'],
            self::GRADES,
            $term->end_date?->toDateString(),
        );

        if ($data['effective_date'] !== $expectedDate) {
            throw ValidationException::withMessages([
                'effective_date' => "The adjusted schedule must use the next common school day, {$expectedDate}.",
            ]);
        }

        $duplicate = ClassScheduleDayAdjustment::where('academic_term_id', $term->id)
            ->where('effective_date', $data['effective_date'])
            ->when($adjustment, fn ($query) => $query->whereKeyNot($adjustment->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'effective_date' => 'An adjustment already exists for this date.',
            ]);
        }

        return $data;
    }

    private function validateAffectedMonday(string $date, AcademicTerm $term): void
    {
        $monday = Carbon::parse($date);

        if (! $monday->isMonday()) {
            throw ValidationException::withMessages([
                'postponed_from_date' => 'The postponed flag-ceremony date must be a Monday.',
            ]);
        }

        if (($term->start_date && $monday->lt($term->start_date)) || ($term->end_date && $monday->gt($term->end_date))) {
            throw ValidationException::withMessages([
                'postponed_from_date' => 'The affected Monday must fall within the selected academic term.',
            ]);
        }

        $allGradesClosed = collect(self::GRADES)
            ->every(fn (int $grade) => ! $this->calendar->isSchoolDay($date, $grade));

        if (! $allGradesClosed) {
            throw ValidationException::withMessages([
                'postponed_from_date' => 'Record the campus-wide holiday or suspension in the Academic Calendar first.',
            ]);
        }
    }
}
