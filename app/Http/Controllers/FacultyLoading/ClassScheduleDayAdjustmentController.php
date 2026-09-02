<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\FacultyLoading\Subject;
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
        abort_unless($request->user()->hasAnyPermission(['faculty_loading.manage', 'faculty_loading.view_own']), 403);

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
                'adjustment_type' => $adjustment->adjustment_type,
                'grade_levels' => $adjustment->gradeLevels(),
                'postponed_from_date' => $adjustment->postponed_from_date?->toDateString(),
                'effective_date' => $adjustment->effective_date->toDateString(),
                'weekday' => $adjustment->effective_date->englishDayOfWeek,
                'reason' => $adjustment->reason,
                'shift_minutes' => $adjustment->shift_minutes,
                'activity_title' => $adjustment->activity_title,
                'activity_start_time' => $adjustment->activity_start_time ? substr((string) $adjustment->activity_start_time, 0, 5) : null,
                'activity_end_time' => $adjustment->activity_end_time ? substr((string) $adjustment->activity_end_time, 0, 5) : null,
                'class_duration_minutes' => $adjustment->class_duration_minutes,
                'day_start_time' => $adjustment->day_start_time ? substr((string) $adjustment->day_start_time, 0, 5) : null,
                'stem_class_duration_minutes' => $adjustment->stem_class_duration_minutes,
                'non_stem_class_duration_minutes' => $adjustment->non_stem_class_duration_minutes,
                'health_break_title' => $adjustment->health_break_title,
                'health_break_start_time' => $adjustment->health_break_start_time ? substr((string) $adjustment->health_break_start_time, 0, 5) : null,
                'health_break_end_time' => $adjustment->health_break_end_time ? substr((string) $adjustment->health_break_end_time, 0, 5) : null,
                'status' => $adjustment->status,
                'created_by' => $adjustment->createdBy?->name,
                'published_by' => $adjustment->publishedBy?->name,
                'published_at' => $adjustment->published_at?->toIso8601String(),
                'cancelled_by' => $adjustment->cancelledBy?->name,
                'cancelled_at' => $adjustment->cancelled_at?->toIso8601String(),
            ]);

        $stemCoverage = $term
            ? [
                'tagged' => Subject::where('school_year_id', $term->school_year_id)->where('is_active', true)->where('is_stem', true)->count(),
                'total' => Subject::where('school_year_id', $term->school_year_id)->where('is_active', true)->count(),
            ]
            : ['tagged' => 0, 'total' => 0];

        return Inertia::render('FacultyLoading/Schedules/DayAdjustments', [
            'term' => $term ? [
                'id' => $term->id,
                'label' => $term->full_label,
                'start_date' => $term->start_date?->toDateString(),
                'end_date' => $term->end_date?->toDateString(),
            ] : null,
            'terms' => $terms,
            'adjustments' => $adjustments,
            'canManage' => $request->user()->hasPermission('faculty_loading.manage'),
            'stemSubjectCoverage' => $stemCoverage,
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
        $warnings = [];

        DB::transaction(function () use ($data, &$warnings) {
            $adjustment = ClassScheduleDayAdjustment::create([
                ...$data,
                'ceremony_start_time' => '07:30',
                'ceremony_end_time' => '08:00',
                'shift_minutes' => $this->hasFlag($data['adjustment_type']) ? 30 : 0,
                'class_duration_minutes' => $this->hasShortenedClasses($data['adjustment_type']) ? 30 : null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            // Validate fit and generated campus conflicts before keeping the draft.
            $warnings = $this->adjustedSchedules->generate($adjustment)['conflict_warnings'] ?? [];
        });

        return back()->with([
            'success' => 'Adjusted-day schedule saved as a draft.',
            'warning' => $warnings ? implode(' ', $warnings) : null,
        ]);
    }

    public function update(Request $request, ClassScheduleDayAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be edited.');

        $data = $this->validatedData($request, $adjustment);
        $warnings = [];

        DB::transaction(function () use ($adjustment, $data, &$warnings) {
            $adjustment->update([
                ...$data,
                'shift_minutes' => $this->hasFlag($data['adjustment_type']) ? 30 : 0,
                'class_duration_minutes' => $this->hasShortenedClasses($data['adjustment_type']) ? 30 : null,
            ]);
            $warnings = $this->adjustedSchedules->generate($adjustment->fresh())['conflict_warnings'] ?? [];
        });

        return back()->with([
            'success' => 'Adjusted-day draft updated.',
            'warning' => $warnings ? implode(' ', $warnings) : null,
        ]);
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

        // Not back() — publishing can now be triggered from the Resolve
        // Conflicts page too, and that page's own controller action aborts
        // once the adjustment is no longer a draft, so back() would bounce
        // the user straight into a 422.
        return redirect()->route('faculty-loading.schedules.day-adjustments.index')
            ->with('success', 'Adjusted-day schedule published and frozen for official printing.');
    }

    /**
     * Grade scope may be changed even after publishing (unlike every other
     * field, which is draft-only) — a published adjustment's frozen
     * snapshot is regenerated and refrozen against the new grade selection
     * so print and every other consumer of schedule_snapshot stay correct.
     */
    public function updateGrades(Request $request, ClassScheduleDayAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status === 'cancelled', 422, 'A cancelled adjustment cannot be edited.');

        $data = $request->validate([
            'grade_levels' => ['required', 'array', 'min:1'],
            'grade_levels.*' => ['integer', 'in:7,8,9,10,11,12'],
        ]);

        $selectedGrades = array_values(array_unique(array_map('intval', $data['grade_levels'])));
        sort($selectedGrades);
        $gradeLevels = $selectedGrades === self::GRADES ? null : $selectedGrades;

        $warnings = [];

        DB::transaction(function () use ($adjustment, $gradeLevels, &$warnings) {
            $locked = ClassScheduleDayAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'cancelled', 422, 'A cancelled adjustment cannot be edited.');

            $locked->update(['grade_levels' => $gradeLevels]);
            $generated = $this->adjustedSchedules->generate($locked->fresh());
            $warnings = $generated['conflict_warnings'] ?? [];

            if ($locked->status === 'published') {
                $locked->update(['schedule_snapshot' => $generated]);
            }
        });

        return back()->with([
            'success' => 'Grade levels updated.',
            'warning' => $warnings ? implode(' ', $warnings) : null,
        ]);
    }

    /**
     * Live-regenerated preview for the draft's conflict-resolution screen —
     * always reflects the current grade selection and overrides, never the
     * frozen snapshot (that only exists once published).
     */
    public function preview(ClassScheduleDayAdjustment $adjustment): JsonResponse
    {
        abort_unless(request()->user()->hasAnyPermission(['faculty_loading.manage', 'faculty_loading.view_own']), 403);

        return response()->json($this->adjustedSchedules->generate($adjustment));
    }

    /**
     * Interactive conflict-resolution screen for a draft — shows the live
     * generated preview plus any conflict_warnings, and lets a manager
     * manually correct a specific entry's time to resolve one before
     * publishing. Draft-only (a published schedule is already frozen).
     */
    public function resolve(ClassScheduleDayAdjustment $adjustment): Response
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be manually adjusted.');

        $adjustment->loadMissing('academicTerm.schoolYear');

        return Inertia::render('FacultyLoading/Schedules/ResolveConflicts', [
            'adjustment' => [
                'id' => $adjustment->id,
                'adjustment_type' => $adjustment->adjustment_type,
                'grade_levels' => $adjustment->gradeLevels(),
                'effective_date' => $adjustment->effective_date->toDateString(),
                'reason' => $adjustment->reason,
            ],
            'term' => [
                'label' => $adjustment->academicTerm->full_label,
                'school_year' => $adjustment->academicTerm->schoolYear?->name,
            ],
            'preview' => $this->adjustedSchedules->generate($adjustment),
        ]);
    }

    /**
     * Manually correct one class entry's displayed time on this adjusted
     * date only, to resolve a flagged conflict before publishing. Draft-only
     * — a published schedule is frozen and can't take new overrides without
     * going back to draft first.
     */
    public function upsertOverride(Request $request, ClassScheduleDayAdjustment $adjustment): JsonResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be manually adjusted.');

        $data = $request->validate([
            'class_schedule_id' => ['required', 'integer', 'exists:class_schedules,id'],
            'override_start_time' => ['required', 'date_format:H:i'],
            'override_end_time' => ['required', 'date_format:H:i'],
        ]);

        if ($data['override_end_time'] <= $data['override_start_time']) {
            throw ValidationException::withMessages([
                'override_end_time' => 'The override end time must be after its start time.',
            ]);
        }

        DB::transaction(function () use ($data, $adjustment) {
            $movingSchedule = ClassSchedule::findOrFail($data['class_schedule_id']);

            // Bump every other entry in the same section whose current
            // (already override-aware) time collides with the mover's new
            // range — a class goes to Unplaced for manual re-placement, a
            // non_teaching block is just removed (no tray entry for it).
            $preview = $this->adjustedSchedules->generate($adjustment);
            $section = collect($preview['grades'])
                ->flatMap(fn (array $grade) => $grade['sections'])
                ->firstWhere('id', $movingSchedule->section_id);

            if ($section) {
                foreach ($section['entries'] as $entry) {
                    if ($entry['id'] === $movingSchedule->id) {
                        continue;
                    }
                    $collides = $entry['start_time'] < $data['override_end_time']
                        && $data['override_start_time'] < $entry['end_time'];
                    if (! $collides) {
                        continue;
                    }

                    $adjustment->unplacedEntries()->updateOrCreate(['class_schedule_id' => $entry['id']], []);
                    $adjustment->overrides()->where('class_schedule_id', $entry['id'])->delete();
                }
            }

            // Providing an explicit time inherently means "place me here
            // now" — clears the mover's own unplaced status, if any (this
            // is how a chip dragged out of the Unplaced tray gets resolved).
            $adjustment->unplacedEntries()->where('class_schedule_id', $data['class_schedule_id'])->delete();

            $adjustment->overrides()->updateOrCreate(
                ['class_schedule_id' => $data['class_schedule_id']],
                ['override_start_time' => $data['override_start_time'], 'override_end_time' => $data['override_end_time']],
            );
        });

        return response()->json($this->adjustedSchedules->generate($adjustment->fresh()));
    }

    public function removeOverride(ClassScheduleDayAdjustment $adjustment, int $classScheduleId): JsonResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be manually adjusted.');

        $adjustment->overrides()->where('class_schedule_id', $classScheduleId)->delete();

        return response()->json($this->adjustedSchedules->generate($adjustment->fresh()));
    }

    /**
     * Manually correct one Recess, White Space, or Wellness band's displayed
     * time for one section on this adjusted date only, or the campus-wide
     * Health Break block. Draft-only, same lifecycle as upsertOverride() but
     * keyed by section+band type instead of a ClassSchedule row (bands have
     * no id of their own).
     */
    public function upsertBandOverride(Request $request, ClassScheduleDayAdjustment $adjustment): JsonResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be manually adjusted.');

        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'band_type' => ['required', 'in:RECESS,WHITE_SPACE,WELLNESS,HEALTH_BREAK'],
            'override_start_time' => ['required', 'date_format:H:i'],
            'override_end_time' => ['required', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['override_end_time'] <= $data['override_start_time']) {
            throw ValidationException::withMessages([
                'override_end_time' => 'The override end time must be after its start time.',
            ]);
        }

        if ($data['band_type'] === 'HEALTH_BREAK') {
            // Health Break is a single campus-wide block declared directly
            // on the adjustment (health_break_start_time/end_time), not a
            // per-section band like Recess/White Space/Wellness — dragging
            // or resizing it in any one section's column updates that one
            // shared value for every section at once.
            $adjustment->update([
                'health_break_title' => $data['title'] ?? $adjustment->health_break_title ?? 'Health Break',
                'health_break_start_time' => $data['override_start_time'],
                'health_break_end_time' => $data['override_end_time'],
            ]);
        } else {
            $adjustment->bandOverrides()->updateOrCreate(
                ['section_id' => $data['section_id'], 'band_type' => $data['band_type']],
                ['override_start_time' => $data['override_start_time'], 'override_end_time' => $data['override_end_time']],
            );
        }

        return response()->json($this->adjustedSchedules->generate($adjustment->fresh()));
    }

    public function removeBandOverride(ClassScheduleDayAdjustment $adjustment, int $sectionId, string $bandType): JsonResponse
    {
        $this->authorize('faculty_loading.manage');
        abort_if($adjustment->status !== 'draft', 422, 'Only draft adjustments can be manually adjusted.');

        if ($bandType === 'HEALTH_BREAK') {
            // No campus default to fall back to — removing it means the
            // block disappears from the day entirely, same as never having
            // declared one (see ClassScheduleDayAdjustment::hasHealthBreak()).
            $adjustment->update([
                'health_break_title' => null,
                'health_break_start_time' => null,
                'health_break_end_time' => null,
            ]);
        } else {
            $adjustment->bandOverrides()->where('section_id', $sectionId)->where('band_type', $bandType)->delete();
        }

        return response()->json($this->adjustedSchedules->generate($adjustment->fresh()));
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
        abort_unless($request->user()->hasAnyPermission(['faculty_loading.manage', 'faculty_loading.view_own']), 403);
        abort_if($adjustment->status === 'cancelled', 404);

        $adjustment->loadMissing([
            'academicTerm.schoolYear',
            'publishedBy:id,name,position,prenominal_title,postnominal_title',
            'createdBy:id,name,position,prenominal_title,postnominal_title',
        ]);
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
            ->first(['id', 'name', 'position', 'prenominal_title', 'postnominal_title']);
        $prepared = $adjustment->publishedBy ?? $adjustment->createdBy;

        return Inertia::render('FacultyLoading/Schedules/PrintAdjustedDay', [
            'adjustment' => [
                'id' => $adjustment->id,
                'adjustment_type' => $adjustment->adjustment_type,
                'grade_levels' => $adjustment->gradeLevels(),
                'postponed_from_date' => $adjustment->postponed_from_date?->toDateString(),
                'effective_date' => $adjustment->effective_date->toDateString(),
                'reason' => $adjustment->reason,
                'status' => $adjustment->status,
                'shift_minutes' => $adjustment->shift_minutes,
                'activity_title' => $adjustment->activity_title,
                'activity_start_time' => $adjustment->activity_start_time ? substr((string) $adjustment->activity_start_time, 0, 5) : null,
                'activity_end_time' => $adjustment->activity_end_time ? substr((string) $adjustment->activity_end_time, 0, 5) : null,
                'class_duration_minutes' => $adjustment->class_duration_minutes,
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
            'adjustment_type' => ['nullable', 'in:flag_ceremony,shortened_classes,flag_ceremony_shortened_classes,shortened_classes_protect_assessments,early_start_stem_split'],
            'grade_levels' => ['required', 'array', 'min:1'],
            'grade_levels.*' => ['integer', 'in:7,8,9,10,11,12'],
            'postponed_from_date' => ['nullable', 'date'],
            'effective_date' => ['required', 'date'],
            'activity_title' => ['nullable', 'string', 'max:255'],
            'activity_start_time' => ['nullable', 'date_format:H:i'],
            'activity_end_time' => ['nullable', 'date_format:H:i'],
            'day_start_time' => ['nullable', 'date_format:H:i'],
            'stem_class_duration_minutes' => ['nullable', 'integer', 'min:10', 'max:60'],
            'non_stem_class_duration_minutes' => ['nullable', 'integer', 'min:10', 'max:60'],
            'health_break_title' => ['nullable', 'string', 'max:255'],
            'health_break_start_time' => ['nullable', 'date_format:H:i'],
            'health_break_end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $data['adjustment_type'] ??= 'flag_ceremony';
        if ($data['adjustment_type'] === 'early_start_stem_split') {
            $data['day_start_time'] ??= '07:00';
            $data['stem_class_duration_minutes'] ??= 50;
            $data['non_stem_class_duration_minutes'] ??= 30;
        } else {
            $data['day_start_time'] = null;
            $data['stem_class_duration_minutes'] = null;
            $data['non_stem_class_duration_minutes'] = null;
        }
        $data['health_break_title'] ??= null;
        $data['health_break_start_time'] ??= null;
        $data['health_break_end_time'] ??= null;
        $selectedGrades = array_values(array_unique(array_map('intval', $data['grade_levels'])));
        sort($selectedGrades);
        // Store null (not the full [7..12] array) when every grade is
        // selected, so gradeLevels() treats it identically to legacy rows
        // created before grade scoping existed.
        $data['grade_levels'] = $selectedGrades === self::GRADES ? null : $selectedGrades;

        $term = AcademicTerm::findOrFail($data['academic_term_id']);
        $effectiveDate = Carbon::parse($data['effective_date']);
        if (($term->start_date && $effectiveDate->lt($term->start_date)) || ($term->end_date && $effectiveDate->gt($term->end_date))) {
            throw ValidationException::withMessages([
                'effective_date' => 'The adjusted date must fall within the selected academic term.',
            ]);
        }

        if ($this->hasFlag($data['adjustment_type'])) {
            if (empty($data['postponed_from_date'])) {
                throw ValidationException::withMessages([
                    'postponed_from_date' => 'The postponed Monday is required for a transferred flag ceremony.',
                ]);
            }

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
        } else {
            $data['postponed_from_date'] = null;
        }

        if ($this->hasShortenedClasses($data['adjustment_type'])) {
            // The early-start STEM-split day doesn't require a declared
            // Official Activity — the point of the day is simply an earlier
            // start, not necessarily freeing time for a campus event.
            if ($data['adjustment_type'] !== 'early_start_stem_split') {
                foreach (['activity_title', 'activity_start_time', 'activity_end_time'] as $field) {
                    if (empty($data[$field])) {
                        throw ValidationException::withMessages([
                            $field => 'This field is required for a shortened-class day.',
                        ]);
                    }
                }

                if ($data['activity_end_time'] <= $data['activity_start_time']) {
                    throw ValidationException::withMessages([
                        'activity_end_time' => 'The activity end time must be after its start time.',
                    ]);
                }
            }

            $allGradesOpen = collect($selectedGrades)
                ->every(fn (int $grade) => $this->calendar->isSchoolDay($data['effective_date'], $grade));
            if (! $allGradesOpen) {
                throw ValidationException::withMessages([
                    'effective_date' => 'The shortened-class adjustment must fall on a school day for every selected grade level.',
                ]);
            }
        } else {
            $data['activity_title'] = null;
            $data['activity_start_time'] = null;
            $data['activity_end_time'] = null;
        }

        if ($data['health_break_title'] || $data['health_break_start_time'] || $data['health_break_end_time']) {
            foreach (['health_break_title', 'health_break_start_time', 'health_break_end_time'] as $field) {
                if (empty($data[$field])) {
                    throw ValidationException::withMessages([
                        $field => 'All three health break fields are required together.',
                    ]);
                }
            }

            if ($data['health_break_end_time'] <= $data['health_break_start_time']) {
                throw ValidationException::withMessages([
                    'health_break_end_time' => 'The health break end time must be after its start time.',
                ]);
            }
        }

        $duplicate = ClassScheduleDayAdjustment::where('academic_term_id', $term->id)
            ->where('effective_date', $data['effective_date'])
            ->where('status', '<>', 'cancelled')
            ->when($adjustment, fn ($query) => $query->whereKeyNot($adjustment->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'effective_date' => 'An adjustment already exists for this date.',
            ]);
        }

        return $data;
    }

    private function hasFlag(string $type): bool
    {
        return in_array($type, ['flag_ceremony', 'flag_ceremony_shortened_classes'], true);
    }

    private function hasShortenedClasses(string $type): bool
    {
        return in_array($type, [
            'shortened_classes',
            'flag_ceremony_shortened_classes',
            'shortened_classes_protect_assessments',
            'early_start_stem_split',
        ], true);
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
