<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\BellScheduleOverride;
use App\Models\FacultyLoading\BellScheduleSetting;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SectionConsultationOverride;
use App\Services\FacultyLoading\ScheduleBlockAvailabilityService;
use App\Services\FacultyLoading\ClassScheduleScopeLockService;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bell Schedule editor — lets Administrators and the CID Chief edit the
 * "constant" daily blocks (flag ceremony, recess, lunch, consultation /
 * home bound, homeroom, class periods) per grade group and day pattern.
 *
 * Edits are stored as overrides on top of SchedulingConstants' hardcoded
 * defaults; a "reset" removes the override and restores the built-in schedule.
 * Changes only affect FUTURE schedule generation — already-plotted
 * class_schedules rows are never touched.
 */
class BellScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleBlockAvailabilityService $availability,
        private readonly ClassScheduleScopeLockService $scopeLocks,
    ) {}

    /** Block types the editor accepts. Only CLASS/ILP_ONLY are teachable. */
    private const TYPES = [
        'CLASS', 'ILP_ONLY', 'FLAG', 'HOMEROOM', 'ADVISING', 'DEAD',
        'RECESS', 'LUNCH', 'CONSULT', 'ACTIVITY', 'WELLNESS', 'OTHER',
    ];

    private function authorizeEditor(): void
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $user->hasRole('CID Chief'), 403);
    }

    /** Special-window settings: shape drives which sub-editor the UI renders.
     *  Wellness (like White Space) has its own dedicated scoped editor on the
     *  Schedules calendar instead of an entry here — see updateWellnessGrade()/
     *  updateWellnessCampus() below. */
    private const SETTING_META = [
        'WEDNESDAY_ALP'            => ['label' => 'Wednesday ALP (campus default)', 'shape' => 'window'],
        'WEDNESDAY_ALP_BY_GRADE'   => ['label' => 'Wednesday ALP — per-grade override', 'shape' => 'grade_windows'],
        'WEDNESDAY_ACTIVITY_START' => ['label' => 'Wednesday Activity Cutoff (per group)', 'shape' => 'group_times'],
        'WEDNESDAY_FULL_GRADES'    => ['label' => 'Grades exempt from the Wednesday cutoff', 'shape' => 'grades'],
        'FRIDAY_FLAG_RETREAT'      => ['label' => 'Friday Flag Retreat Ceremony',   'shape' => 'window'],
        'FRIDAY_ILA_GRADES'        => ['label' => 'Grades with no in-person Friday classes (ILA)', 'shape' => 'grades'],
        'GRADE8_OVERFLOW_SLOTS'    => ['label' => 'Grade 8 Overflow Periods (per day)', 'shape' => 'day_slots'],
    ];

    private const GROUPS = ['G7G8', 'G9G10', 'G11G12'];

    public function index(): Response
    {
        $this->authorizeEditor();

        $overridden = BellScheduleOverride::pluck('timetable_key')->all();
        $editedSettings = BellScheduleSetting::pluck('setting_key')->all();

        $timetables = collect(SchedulingConstants::EDITABLE_TIMETABLES)
            ->map(fn ($label, $key) => [
                'key'           => $key,
                'label'         => $label,
                'rows'          => SchedulingConstants::effectiveTimetableRows($key),
                'is_overridden' => in_array($key, $overridden, true),
            ])
            ->values();

        $settings = collect(self::SETTING_META)
            ->map(fn ($meta, $key) => [
                'key'           => $key,
                'label'         => $meta['label'],
                'shape'         => $meta['shape'],
                'value'         => SchedulingConstants::setting($key),
                'is_overridden' => in_array($key, $editedSettings, true),
            ])
            ->values();

        return Inertia::render('FacultyLoading/BellSchedule/Index', [
            'timetables'    => $timetables,
            'types'         => self::TYPES,
            'teachingTypes' => ['CLASS', 'ILP_ONLY'],
            'settings'      => $settings,
            'groups'        => self::GROUPS,
            'grades'        => [7, 8, 9, 10, 11, 12],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeEditor();

        $data = $request->validate([
            'timetable_key'   => 'required|string|in:'.implode(',', array_keys(SchedulingConstants::EDITABLE_TIMETABLES)),
            'rows'            => 'required|array|min:1',
            'rows.*.start'    => 'required|date_format:H:i',
            'rows.*.end'      => 'required|date_format:H:i|after:rows.*.start',
            'rows.*.type'     => 'required|in:'.implode(',', self::TYPES),
            'rows.*.label'    => 'required|string|max:60',
        ]);
        $this->assertScheduleScopeUnlocked($this->resolveTermId($request), 'whole');

        // Reject overlapping blocks — the timetable must read cleanly top to bottom.
        $rows = collect($data['rows'])
            ->sortBy(fn ($r) => $r['start'])
            ->values()
            ->all();
        for ($i = 1; $i < count($rows); $i++) {
            if ($rows[$i]['start'] < $rows[$i - 1]['end']) {
                return response()->json([
                    'message' => "Blocks overlap: {$rows[$i - 1]['label']} ({$rows[$i - 1]['start']}–{$rows[$i - 1]['end']}) "
                        ."and {$rows[$i]['label']} ({$rows[$i]['start']}–{$rows[$i]['end']}).",
                ], 422);
            }
        }

        // Normalise to the {start,end,type,label} shape the engine expects.
        $clean = array_map(fn ($r) => [
            'start' => $r['start'],
            'end'   => $r['end'],
            'type'  => $r['type'],
            'label' => $r['label'],
        ], $rows);

        BellScheduleOverride::updateOrCreate(
            ['timetable_key' => $data['timetable_key']],
            ['rows' => $clean, 'updated_by' => Auth::id()],
        );
        SchedulingConstants::flushOverrideCache();

        return response()->json(['message' => 'Bell schedule updated. New generations will use these times.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $this->authorizeEditor();

        $data = $request->validate([
            'timetable_key' => 'required|string|in:'.implode(',', array_keys(SchedulingConstants::EDITABLE_TIMETABLES)),
        ]);
        $this->assertScheduleScopeUnlocked($this->resolveTermId($request), 'whole');

        BellScheduleOverride::where('timetable_key', $data['timetable_key'])->delete();
        SchedulingConstants::flushOverrideCache();

        return response()->json([
            'message' => 'Reset to the built-in schedule.',
            'rows'    => SchedulingConstants::defaultTimetableRows($data['timetable_key']),
        ]);
    }

    // ── Special windows ──────────────────────────────────────────────────────

    public function updateSetting(Request $request): JsonResponse
    {
        $this->authorizeEditor();

        $data = $request->validate([
            'setting_key' => 'required|string|in:'.implode(',', SchedulingConstants::SETTING_KEYS),
            'value'       => 'required',
        ]);
        $this->assertScheduleScopeUnlocked($this->resolveTermId($request), 'whole');

        [$ok, $clean, $error] = $this->validateSettingValue($data['setting_key'], $data['value']);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        BellScheduleSetting::updateOrCreate(
            ['setting_key' => $data['setting_key']],
            ['value' => $clean, 'updated_by' => Auth::id()],
        );
        SchedulingConstants::flushOverrideCache();

        return response()->json(['message' => 'Saved. New generations will use these times.']);
    }

    public function resetSetting(Request $request): JsonResponse
    {
        $this->authorizeEditor();

        $data = $request->validate([
            'setting_key' => 'required|string|in:'.implode(',', SchedulingConstants::SETTING_KEYS),
        ]);
        $this->assertScheduleScopeUnlocked($this->resolveTermId($request), 'whole');

        BellScheduleSetting::where('setting_key', $data['setting_key'])->delete();
        SchedulingConstants::flushOverrideCache();

        return response()->json([
            'message' => 'Reset to the built-in default.',
            'value'   => SchedulingConstants::defaultSetting($data['setting_key']),
        ]);
    }

    // ── White Space ───────────────────────────────────────────────────────────
    // Section scope lives on Section::WHITE_SPACE_OVERRIDE_COLUMNS (see
    // SectionController::updateDayWhiteSpace()) — these two cover the other
    // two scopes, both stored in bell_schedule_settings. Unlike updateSetting()
    // (which replaces a setting's entire value), these merge a single
    // grade+day or day into whatever's already saved, since the calendar
    // popover only ever edits one day at a time.

    public function updateWhiteSpaceGrade(Request $request, int $grade, string $day): JsonResponse
    {
        $this->authorizeEditor();

        if ($grade < 7 || $grade > 12) {
            return response()->json(['message' => 'Invalid grade.'], 422);
        }
        if (! in_array($day, SchedulingConstants::DAYS, true)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$ok, $start, $end, $error] = $this->validateOptionalTimeRange($request);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        $termId = $this->resolveTermId($request);
        $this->assertScheduleScopeUnlocked($termId, 'grade', $grade);
        $window = $start !== null
            ? ['start' => $start, 'end' => $end]
            : (SchedulingConstants::setting('WHITE_SPACE_CAMPUS')[$day] ?? null);
        $this->assertScopeAvailable($termId, 'grade', 'white_space', $day, $window, $grade);

        $value = SchedulingConstants::setting('WHITE_SPACE_BY_GRADE');
        if ($start === null) {
            unset($value[$grade][$day]);
            if (empty($value[$grade])) {
                unset($value[$grade]);
            }
        } else {
            $value[$grade][$day] = ['start' => $start, 'end' => $end];
        }

        BellScheduleSetting::updateOrCreate(
            ['setting_key' => 'WHITE_SPACE_BY_GRADE'],
            ['value' => $value, 'updated_by' => Auth::id()],
        );
        SchedulingConstants::flushOverrideCache();

        return response()->json([
            'message' => $start === null
                ? "White Space cleared for Grade {$grade} on {$day}."
                : "White Space set for Grade {$grade} on {$day} ({$start}–{$end}).",
        ]);
    }

    public function updateWhiteSpaceCampus(Request $request, string $day): JsonResponse
    {
        $this->authorizeEditor();

        if (! in_array($day, SchedulingConstants::DAYS, true)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$ok, $start, $end, $error] = $this->validateOptionalTimeRange($request);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        $termId = $this->resolveTermId($request);
        $this->assertScheduleScopeUnlocked($termId, 'whole');
        $window = $start !== null ? ['start' => $start, 'end' => $end] : null;
        $this->assertScopeAvailable($termId, 'campus', 'white_space', $day, $window);

        $value = SchedulingConstants::setting('WHITE_SPACE_CAMPUS');
        if ($start === null) {
            unset($value[$day]);
        } else {
            $value[$day] = ['start' => $start, 'end' => $end];
        }

        BellScheduleSetting::updateOrCreate(
            ['setting_key' => 'WHITE_SPACE_CAMPUS'],
            ['value' => $value, 'updated_by' => Auth::id()],
        );
        SchedulingConstants::flushOverrideCache();

        return response()->json([
            'message' => $start === null
                ? "Campus-wide White Space cleared for {$day}."
                : "Campus-wide White Space set for {$day} ({$start}–{$end}).",
        ]);
    }

    // ── Wellness ──────────────────────────────────────────────────────────────
    // Same merge-one-day-at-a-time shape as White Space above — section scope
    // lives on Section::WELLNESS_OVERRIDE_COLUMNS (see
    // SectionController::updateDayWellness()).

    public function updateWellnessGrade(Request $request, int $grade, string $day): JsonResponse
    {
        $this->authorizeEditor();

        if ($grade < 7 || $grade > 12) {
            return response()->json(['message' => 'Invalid grade.'], 422);
        }
        if (! in_array($day, SchedulingConstants::DAYS, true)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$ok, $start, $end, $error] = $this->validateOptionalTimeRange($request);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        $termId = $this->resolveTermId($request);
        $this->assertScheduleScopeUnlocked($termId, 'grade', $grade);
        $window = $start !== null ? ['start' => $start, 'end' => $end] : $this->wellnessCampusFallback($grade, $day);
        $this->assertScopeAvailable($termId, 'grade', 'wellness', $day, $window, $grade);

        $value = SchedulingConstants::setting('WELLNESS_BY_GRADE');
        if ($start === null) {
            unset($value[$grade][$day]);
            if (empty($value[$grade])) {
                unset($value[$grade]);
            }
        } else {
            $value[$grade][$day] = ['start' => $start, 'end' => $end];
        }

        BellScheduleSetting::updateOrCreate(
            ['setting_key' => 'WELLNESS_BY_GRADE'],
            ['value' => $value, 'updated_by' => Auth::id()],
        );
        SchedulingConstants::flushOverrideCache();

        return response()->json([
            'message' => $start === null
                ? "Wellness cleared for Grade {$grade} on {$day}."
                : "Wellness set for Grade {$grade} on {$day} ({$start}–{$end}).",
        ]);
    }

    public function updateWellnessCampus(Request $request, string $day): JsonResponse
    {
        $this->authorizeEditor();

        if (! in_array($day, SchedulingConstants::DAYS, true)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$ok, $start, $end, $error] = $this->validateOptionalTimeRange($request);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        $termId = $this->resolveTermId($request);
        $this->assertScheduleScopeUnlocked($termId, 'whole');
        $window = $start !== null
            ? ['start' => $start, 'end' => $end]
            : (SchedulingConstants::defaultSetting('WELLNESS_CAMPUS')[$day] ?? null);
        $this->assertScopeAvailable($termId, 'campus', 'wellness', $day, $window);

        $value = SchedulingConstants::setting('WELLNESS_CAMPUS');
        if ($start === null) {
            unset($value[$day]);
        } else {
            $value[$day] = ['start' => $start, 'end' => $end];
        }

        BellScheduleSetting::updateOrCreate(
            ['setting_key' => 'WELLNESS_CAMPUS'],
            ['value' => $value, 'updated_by' => Auth::id()],
        );
        SchedulingConstants::flushOverrideCache();

        return response()->json([
            'message' => $start === null
                ? "Campus-wide Wellness cleared for {$day}."
                : "Campus-wide Wellness set for {$day} ({$start}–{$end}).",
        ]);
    }

    // ── Consultation / Home Bound (per-grade-per-day override) ────────────────
    // Grade-only — no section scope, Consultation was always one grade-wide
    // row. This layers a genuine per-day override on top of the shared
    // TUEFRI_730_* row so Tue/Wed/Thu/Fri stop being tied together (see
    // SchedulingConstants::getConsultationOverride()). "Clear" removes the
    // override, reverting to whatever the shared literal row says for that day.

    public function updateConsultationGrade(Request $request, int $grade, string $day): JsonResponse
    {
        $this->authorizeEditor();

        if ($grade < 7 || $grade > 12) {
            return response()->json(['message' => 'Invalid grade.'], 422);
        }
        if (! in_array($day, SchedulingConstants::DAYS, true)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$ok, $start, $end, $error] = $this->validateOptionalTimeRange($request);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        $termId = $this->resolveTermId($request);
        $this->assertScheduleScopeUnlocked($termId, 'grade', $grade);
        $window = $start !== null ? ['start' => $start, 'end' => $end] : $this->consultationGradeFallback($grade, $day);
        $this->assertScopeAvailable($termId, 'grade', 'consultation', $day, $window, $grade);

        $value = SchedulingConstants::setting('CONSULTATION_BY_GRADE_DAY');
        if ($start === null) {
            unset($value[$grade][$day]);
            if (empty($value[$grade])) {
                unset($value[$grade]);
            }
        } else {
            $value[$grade][$day] = ['start' => $start, 'end' => $end];
        }

        BellScheduleSetting::updateOrCreate(
            ['setting_key' => 'CONSULTATION_BY_GRADE_DAY'],
            ['value' => $value, 'updated_by' => Auth::id()],
        );
        SchedulingConstants::flushOverrideCache();

        return response()->json([
            'message' => $start === null
                ? "Consultation reset to the shared schedule for Grade {$grade} on {$day}."
                : "Consultation set for Grade {$grade} on {$day} ({$start}–{$end}).",
        ]);
    }

    public function updateConsultationSection(Request $request, Section $section, string $day): JsonResponse
    {
        $this->authorizeEditor();
        if (! in_array($day, SchedulingConstants::DAYS, true)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$ok, $start, $end, $error] = $this->validateOptionalTimeRange($request);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        $termId = $this->resolveTermId($request);
        $this->assertScheduleScopeUnlocked($termId, 'section', null, (int) $section->id);
        $window = $start !== null
            ? ['start' => $start, 'end' => $end]
            : SchedulingConstants::getConsultationWindow((int) $section->levelid, $day);
        $this->assertScopeAvailable($termId, 'section', 'consultation', $day, $window, null, (int) $section->id);

        if ($start === null) {
            SectionConsultationOverride::where('section_id', $section->id)->where('day_of_week', $day)->delete();
        } else {
            SectionConsultationOverride::updateOrCreate(
                ['section_id' => $section->id, 'day_of_week' => $day],
                ['start_time' => $start, 'end_time' => $end, 'updated_by' => Auth::id()],
            );
        }

        return response()->json(['message' => $start === null
            ? "Consultation override cleared for {$section->sectionname} on {$day}."
            : "Consultation set for {$section->sectionname} on {$day} ({$start}–{$end})."]);
    }

    public function updateConsultationCampus(Request $request, string $day): JsonResponse
    {
        $this->authorizeEditor();
        if (! in_array($day, SchedulingConstants::DAYS, true)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$ok, $start, $end, $error] = $this->validateOptionalTimeRange($request);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        $termId = $this->resolveTermId($request);
        $this->assertScheduleScopeUnlocked($termId, 'whole');
        $sections = $termId ? $this->availability->affectedSections($termId, 'campus', 'consultation', $day) : collect();
        if ($termId && $start !== null) {
            $this->availability->assertAvailable($sections, $termId, $day, ['start' => $start, 'end' => $end]);
        } elseif ($termId) {
            foreach ($sections->groupBy('levelid') as $grade => $gradeSections) {
                $this->availability->assertAvailable(
                    $gradeSections,
                    $termId,
                    $day,
                    SchedulingConstants::getDefaultConsultationWindow((int) $grade, $day),
                );
            }
        }

        $this->mergeDaySetting('CONSULTATION_CAMPUS_DAY', $day, $start, $end);

        return response()->json(['message' => $start === null
            ? "Campus-wide Consultation reset for {$day}."
            : "Campus-wide Consultation set for {$day} ({$start}–{$end})."]);
    }

    public function updateBreakGrade(Request $request, string $type, int $grade, string $day): JsonResponse
    {
        $this->authorizeEditor();
        if (! in_array($type, ['lunch', 'recess'], true) || $grade < 7 || $grade > 12 || ! in_array($day, SchedulingConstants::DAYS, true)) {
            return response()->json(['message' => 'Invalid break scope.'], 422);
        }
        [$ok, $start, $end, $error] = $this->validateOptionalTimeRange($request);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        $termId = $this->resolveTermId($request);
        $this->assertScheduleScopeUnlocked($termId, 'grade', $grade);
        $window = $start !== null
            ? ['start' => $start, 'end' => $end]
            : $this->breakGradeFallback($type, $grade, $day);
        $this->assertScopeAvailable($termId, 'grade', $type, $day, $window, $grade);
        $this->mergeGradeDaySetting(strtoupper($type).'_BY_GRADE_DAY', $grade, $day, $start, $end);

        return response()->json(['message' => ucfirst($type).' updated for Grade '.$grade.' on '.$day.'.']);
    }

    public function updateBreakCampus(Request $request, string $type, string $day): JsonResponse
    {
        $this->authorizeEditor();
        if (! in_array($type, ['lunch', 'recess'], true) || ! in_array($day, SchedulingConstants::DAYS, true)) {
            return response()->json(['message' => 'Invalid break scope.'], 422);
        }
        [$ok, $start, $end, $error] = $this->validateOptionalTimeRange($request);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        $termId = $this->resolveTermId($request);
        $this->assertScheduleScopeUnlocked($termId, 'whole');
        $sections = $termId ? $this->availability->affectedSections($termId, 'campus', $type, $day) : collect();
        if ($termId && $start !== null) {
            $this->availability->assertAvailable($sections, $termId, $day, ['start' => $start, 'end' => $end]);
        } elseif ($termId) {
            foreach ($sections->groupBy('levelid') as $grade => $gradeSections) {
                $fallback = $type === 'lunch'
                    ? SchedulingConstants::getLunch((int) $grade, $day)
                    : (SchedulingConstants::getRecess((int) $grade, $day)[0] ?? null);
                $this->availability->assertAvailable($gradeSections, $termId, $day, $fallback);
            }
        }
        $this->mergeDaySetting(strtoupper($type).'_CAMPUS_DAY', $day, $start, $end);

        return response()->json(['message' => 'Campus-wide '.ucfirst($type).' updated for '.$day.'.']);
    }

    private function resolveTermId(Request $request): ?int
    {
        $validated = $request->validate(['academic_term_id' => 'nullable|integer|exists:academic_terms,id']);
        $termId = $validated['academic_term_id'] ?? AcademicTerm::where('is_current', true)->value('id');

        return $termId ? (int) $termId : null;
    }

    private function assertScheduleScopeUnlocked(?int $termId, string $scopeType, ?int $grade = null, ?int $sectionId = null): void
    {
        if (! $termId) {
            return;
        }

        $term = AcademicTerm::findOrFail($termId);
        $this->scopeLocks->assertScopeUnlocked($term, $scopeType, $grade, $sectionId);
    }

    private function assertScopeAvailable(?int $termId, string $scope, string $type, string $day, ?array $window, ?int $grade = null, ?int $sectionId = null): void
    {
        if (! $termId) {
            return;
        }
        $sections = $this->availability->affectedSections($termId, $scope, $type, $day, $grade, $sectionId);
        $this->availability->assertAvailable($sections, $termId, $day, $window);
    }

    private function consultationGradeFallback(int $grade, string $day): ?array
    {
        return SchedulingConstants::setting('CONSULTATION_CAMPUS_DAY')[$day]
            ?? SchedulingConstants::getDefaultConsultationWindow($grade, $day);
    }

    private function wellnessCampusFallback(int $grade, string $day): ?array
    {
        if ($day === 'Wednesday' && in_array($grade, SchedulingConstants::wednesdayFullGrades(), true)) {
            return null;
        }

        return SchedulingConstants::setting('WELLNESS_CAMPUS')[$day] ?? null;
    }

    private function breakGradeFallback(string $type, int $grade, string $day): ?array
    {
        $campus = SchedulingConstants::setting(strtoupper($type).'_CAMPUS_DAY')[$day] ?? null;
        if ($campus) {
            return $campus;
        }

        return $type === 'lunch'
            ? SchedulingConstants::getLunch($grade, $day)
            : (SchedulingConstants::getRecess($grade, $day)[0] ?? null);
    }

    private function mergeDaySetting(string $key, string $day, ?string $start, ?string $end): void
    {
        $value = SchedulingConstants::setting($key);
        if ($start === null) {
            unset($value[$day]);
        } else {
            $value[$day] = ['start' => $start, 'end' => $end];
        }
        BellScheduleSetting::updateOrCreate(['setting_key' => $key], ['value' => $value, 'updated_by' => Auth::id()]);
        SchedulingConstants::flushOverrideCache();
    }

    private function mergeGradeDaySetting(string $key, int $grade, string $day, ?string $start, ?string $end): void
    {
        $value = SchedulingConstants::setting($key);
        if ($start === null) {
            unset($value[$grade][$day]);
            if (empty($value[$grade])) {
                unset($value[$grade]);
            }
        } else {
            $value[$grade][$day] = ['start' => $start, 'end' => $end];
        }
        BellScheduleSetting::updateOrCreate(['setting_key' => $key], ['value' => $value, 'updated_by' => Auth::id()]);
        SchedulingConstants::flushOverrideCache();
    }

    /** @return array{0:bool,1:?string,2:?string,3:?string} [ok, start, end, error] */
    private function validateOptionalTimeRange(Request $request): array
    {
        $data = $request->validate([
            'start' => 'nullable|date_format:H:i',
            'end'   => 'nullable|date_format:H:i|after:start',
        ]);

        $startIsNull = ($data['start'] ?? null) === null;
        $endIsNull   = ($data['end'] ?? null) === null;
        if ($startIsNull !== $endIsNull) {
            return [false, null, null, 'Provide both a start and end time, or leave both blank to clear.'];
        }

        return [true, $data['start'] ?? null, $data['end'] ?? null, null];
    }

    /**
     * Per-shape validation + normalisation for a special-window setting.
     *
     * @return array{0:bool,1:mixed,2:?string} [ok, cleanValue, error]
     */
    private function validateSettingValue(string $key, mixed $value): array
    {
        $shape = self::SETTING_META[$key]['shape'] ?? null;
        $isTime = fn ($t) => is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
        $window = function ($w) use ($isTime) {
            return is_array($w) && $isTime($w['start'] ?? null) && $isTime($w['end'] ?? null)
                && $w['start'] < $w['end'];
        };

        switch ($shape) {
            case 'window':
                if (! $window($value)) {
                    return [false, null, 'Start and end must be valid times with end after start.'];
                }

                return [true, ['start' => $value['start'], 'end' => $value['end']], null];

            case 'grade_windows':
                // { "8": {start,end}, ... } — every key a grade 7–12, every value a window.
                $clean = [];
                foreach ((array) $value as $grade => $w) {
                    $g = (int) $grade;
                    if ($g < 7 || $g > 12 || ! $window($w)) {
                        return [false, null, "Invalid ALP window for grade {$grade}."];
                    }
                    $clean[$g] = ['start' => $w['start'], 'end' => $w['end']];
                }

                return [true, $clean, null];

            case 'group_times':
                $clean = [];
                foreach (self::GROUPS as $group) {
                    $t = $value[$group] ?? null;
                    if (! $isTime($t)) {
                        return [false, null, "Invalid cutoff time for {$group}."];
                    }
                    $clean[$group] = $t;
                }

                return [true, $clean, null];

            case 'grades':
                $clean = [];
                foreach ((array) $value as $g) {
                    $g = (int) $g;
                    if ($g < 7 || $g > 12) {
                        return [false, null, 'Grades must be 7–12.'];
                    }
                    $clean[] = $g;
                }

                return [true, array_values(array_unique($clean)), null];

            case 'day_slots':
                // { "Tuesday": [ {start,end,type,label}, ... ], ... }
                $clean = [];
                foreach ((array) $value as $day => $rows) {
                    if (! in_array($day, SchedulingConstants::DAYS, true)) {
                        return [false, null, "Unknown day: {$day}."];
                    }
                    $dayRows = [];
                    foreach ((array) $rows as $r) {
                        if (! $window($r) || ! in_array($r['type'] ?? '', self::TYPES, true) || empty($r['label'])) {
                            return [false, null, "Invalid overflow block on {$day}."];
                        }
                        $dayRows[] = [
                            'start' => $r['start'], 'end' => $r['end'],
                            'type'  => $r['type'],  'label' => substr((string) $r['label'], 0, 60),
                        ];
                    }
                    // Reject overlaps within a day.
                    usort($dayRows, fn ($a, $b) => $a['start'] <=> $b['start']);
                    for ($i = 1; $i < count($dayRows); $i++) {
                        if ($dayRows[$i]['start'] < $dayRows[$i - 1]['end']) {
                            return [false, null, "Overflow blocks overlap on {$day}."];
                        }
                    }
                    if ($dayRows !== []) {
                        $clean[$day] = $dayRows;
                    }
                }

                return [true, $clean, null];

            default:
                return [false, null, 'Unknown setting.'];
        }
    }
}
