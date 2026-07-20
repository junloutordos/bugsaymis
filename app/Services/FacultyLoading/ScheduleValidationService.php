<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\FacultyLoading\TeacherOfficialTime;

/**
 * ScheduleValidationService
 *
 * Orchestrates all schedule validations before a class schedule is created
 * or updated.  Returns a structured result that controllers/endpoints can
 * use to decide whether to proceed, warn, or block the operation.
 *
 * Result structure:
 * [
 *   'valid'      => bool,      // false = BLOCK (hard errors exist)
 *   'errors'     => string[],  // must be fixed before saving
 *   'warnings'   => string[],  // can be saved with acknowledgement
 *   'conflicts'  => array,     // raw conflict detail from ConflictDetectionService
 *   'load_check' => array,     // faculty load validation summary
 *   'hours_check'=> array,     // daily hours validation result
 * ]
 */
class ScheduleValidationService
{
    public function __construct(
        private readonly ConflictDetectionService $conflicts,
        private readonly LoadComputationService   $loads,
    ) {}

    /**
     * Validate a proposed schedule slot.
     *
     * @param array $data {
     *   faculty_id:       int,
     *   subject_id:       int,
     *   section_id:       int,
     *   classroom_id:     int,
     *   school_year_id:   int,
     *   academic_term_id: int,
     *   day_of_week:      string,
     *   start_time:       string  (H:i or H:i:s),
     *   end_time:         string  (H:i or H:i:s),
     * }
     * @param int|array<int>|null $excludeScheduleId Existing schedule ids to skip
     */
    public function validate(array $data, int|array|null $excludeScheduleId = null): array
    {
        $errors   = [];
        $warnings = [];

        // ── 1. Basic input validation ────────────────────────────────────────
        $inputErrors = $this->validateInputs($data);
        if (! empty($inputErrors)) {
            return [
                'valid'       => false,
                'errors'      => $inputErrors,
                'warnings'    => [],
                'conflicts'   => [],
                'load_check'  => [],
                'hours_check' => [],
            ];
        }

        // ── 2. Conflict detection (hard block) ───────────────────────────────
        $conflictResult = $this->conflicts->detectAllConflicts($data, $excludeScheduleId);
        if ($conflictResult['has_conflicts']) {
            $errors = array_merge($errors, $conflictResult['messages']);
        }

        // ── 3. Daily teaching hours check ────────────────────────────────────
        $projectedHours = $this->conflicts->getProjectedDailyHours(
            $data['faculty_id'],
            $data['day_of_week'],
            $data['start_time'],
            $data['end_time'],
            $data['academic_term_id'],
            $excludeScheduleId
        );
        $hoursCheck = $this->loads->validateDailyTeachingHours($projectedHours);

        if ($hoursCheck['level'] === 'exceeded') {
            $errors[] = $hoursCheck['message'];
        } elseif ($hoursCheck['level'] === 'warning') {
            $warnings[] = $hoursCheck['message'];
        }

        // ── 4. Load limit checks (warnings only — overload needs approval) ───
        $loadCheck = $this->validateFacultyLoadLimits($data, $excludeScheduleId);
        $errors    = array_merge($errors,   $loadCheck['errors']);
        $warnings  = array_merge($warnings, $loadCheck['warnings']);

        // ── 5. Section break-time check ──────────────────────────────────────
        $section = Section::find($data['section_id']);
        if ($section) {
            $breakError = $this->checkBreakTimeConflict($section, $data['start_time'], $data['end_time']);
            if ($breakError) {
                $errors[] = $breakError;
            }
        }

        // ── 5b. Teacher official-time check ──────────────────────────────────
        $officialTimeError = $this->checkOfficialTime($data['faculty_id'], $data['day_of_week'], $data['start_time'], $data['end_time']);
        if ($officialTimeError) {
            $errors[] = $officialTimeError;
        }

        // ── 6. Classroom availability ────────────────────────────────────────
        $classroom = Classroom::find($data['classroom_id']);
        if ($classroom && ! $classroom->is_available) {
            $errors[] = "Classroom '{$classroom->name}' is currently marked as unavailable.";
        }

        // ── 7. Subject–room type compatibility ──────────────────────────────
        if ($classroom) {
            $subject = Subject::find($data['subject_id']);
            if ($subject) {
                $roomWarning = $this->checkRoomTypeCompatibility($subject, $classroom);
                if ($roomWarning) {
                    $warnings[] = $roomWarning;
                }
            }
        }

        // ── 8. Room capacity vs. section capacity ────────────────────────────
        if ($classroom && $section && $section->capacity && $classroom->capacity < $section->capacity) {
            $warnings[] = "Room '{$classroom->name}' capacity ({$classroom->capacity}) is smaller than "
                . "section '{$section->sectionname}' capacity ({$section->capacity}).";
        }

        // ── 9. Day-pattern consistency (soft — never blocks) ─────────────────
        $patternWarning = $this->checkDayPatternConsistency($data, $excludeScheduleId);
        if ($patternWarning) {
            $warnings[] = $patternWarning;
        }

        return [
            'valid'       => empty($errors),
            'errors'      => $errors,
            'warnings'    => $warnings,
            'conflicts'   => $conflictResult,
            'load_check'  => $loadCheck,
            'hours_check' => $hoursCheck,
        ];
    }

    /**
     * Validate a proposed NON-TEACHING block (consultation, research, advising,
     * meeting, …). Runs only the conflict axes for the references that are
     * actually present (faculty and/or section, optional room) plus the
     * teacher official-time window. Subject, load-limit, teaching-hours and
     * break-time checks don't apply — blocks carry no load units.
     *
     * @param array $data {
     *   faculty_id:       int|null,
     *   section_id:       int|null,
     *   classroom_id:     int|null,
     *   academic_term_id: int,
     *   day_of_week:      string,
     *   start_time:       string,
     *   end_time:         string,
     * }
     */
    public function validateNonTeaching(array $data, int|array|null $excludeScheduleId = null): array
    {
        $errors   = [];
        $warnings = [];

        foreach (['academic_term_id', 'day_of_week', 'start_time', 'end_time'] as $field) {
            if (empty($data[$field])) {
                $errors[] = "Missing required field: {$field}.";
            }
        }
        if (empty($data['faculty_id']) && empty($data['section_id'])) {
            $errors[] = 'A non-teaching block needs a faculty member and/or a section.';
        }
        if (! empty($data['start_time']) && ! empty($data['end_time']) && $data['start_time'] >= $data['end_time']) {
            $errors[] = 'Start time must be earlier than end time.';
        }
        $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        if (! empty($data['day_of_week']) && ! in_array($data['day_of_week'], $validDays)) {
            $errors[] = "Invalid day_of_week: '{$data['day_of_week']}'.";
        }

        if (! empty($errors)) {
            return [
                'valid'       => false,
                'errors'      => $errors,
                'warnings'    => [],
                'conflicts'   => [],
                'load_check'  => [],
                'hours_check' => [],
            ];
        }

        $day    = $data['day_of_week'];
        $start  = $data['start_time'];
        $end    = $data['end_time'];
        $termId = (int) $data['academic_term_id'];

        $conflictSets = ['faculty' => collect(), 'room' => collect(), 'section' => collect()];

        if (! empty($data['faculty_id'])) {
            $conflictSets['faculty'] = $this->conflicts->detectFacultyConflict(
                (int) $data['faculty_id'], $day, $start, $end, $termId, $excludeScheduleId
            );
        }
        if (! empty($data['classroom_id'])) {
            $conflictSets['room'] = $this->conflicts->detectRoomConflict(
                (int) $data['classroom_id'], $day, $start, $end, $termId, $excludeScheduleId
            );
        }
        if (! empty($data['section_id'])) {
            $conflictSets['section'] = $this->conflicts->detectSectionConflict(
                (int) $data['section_id'], $day, $start, $end, $termId, $excludeScheduleId
            );
        }

        foreach ($conflictSets['faculty'] as $c) {
            $errors[] = "Faculty conflict: faculty already has '{$this->conflictLabel($c)}' on {$c->day_of_week} {$c->start_time}–{$c->end_time}.";
        }
        foreach ($conflictSets['room'] as $c) {
            $errors[] = "Room conflict: {$c->classroom?->name} is already booked for '{$this->conflictLabel($c)}' on {$c->day_of_week} {$c->start_time}–{$c->end_time}.";
        }
        foreach ($conflictSets['section'] as $c) {
            $errors[] = "Section conflict: section already has '{$this->conflictLabel($c)}' on {$c->day_of_week} {$c->start_time}–{$c->end_time}.";
        }

        if (! empty($data['faculty_id'])) {
            $officialTimeError = $this->checkOfficialTime((int) $data['faculty_id'], $day, $start, $end);
            if ($officialTimeError) {
                $errors[] = $officialTimeError;
            }
        }

        return [
            'valid'       => empty($errors),
            'errors'      => $errors,
            'warnings'    => $warnings,
            'conflicts'   => [
                'has_conflicts' => $conflictSets['faculty']->isNotEmpty()
                                || $conflictSets['room']->isNotEmpty()
                                || $conflictSets['section']->isNotEmpty(),
                'conflicts'     => $conflictSets,
            ],
            'load_check'  => [],
            'hours_check' => [],
        ];
    }

    /** Display label for a conflicting row — subject for classes, title for blocks. */
    private function conflictLabel($schedule): string
    {
        return $schedule->subject?->name ?? $schedule->title ?? 'another entry';
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function validateInputs(array $data): array
    {
        $errors = [];

        $required = ['faculty_id', 'subject_id', 'section_id', 'classroom_id',
                     'academic_term_id', 'day_of_week', 'start_time', 'end_time'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Missing required field: {$field}.";
            }
        }

        if (! empty($data['start_time']) && ! empty($data['end_time'])) {
            if ($data['start_time'] >= $data['end_time']) {
                $errors[] = 'Start time must be earlier than end time.';
            }
        }

        $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        if (! empty($data['day_of_week']) && ! in_array($data['day_of_week'], $validDays)) {
            $errors[] = "Invalid day_of_week: '{$data['day_of_week']}'.";
        }

        return $errors;
    }

    private function validateFacultyLoadLimits(array $data, int|array|null $excludeScheduleId = null): array
    {
        $errors   = [];
        $warnings = [];

        $load = FacultyLoad::where('user_id', $data['faculty_id'])
            ->where('academic_term_id', $data['academic_term_id'])
            ->first();

        if (! $load) {
            $warnings[] = 'No faculty load record found for this term. One will be created automatically.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Admin units cap
        $adminResult = $this->loads->validateAdminLoad((float) $load->admin_units);
        if (! $adminResult['valid']) {
            $errors[] = $adminResult['message'];
        }

        // Research units cap
        $researchResult = $this->loads->validateResearchLoad((float) $load->research_units);
        if (! $researchResult['valid']) {
            $errors[] = $researchResult['message'];
        }

        // Overload warning — load units come from load assignments, so
        // moving/editing an existing schedule ($excludeScheduleId set) never
        // changes the load; only new placements surface the reminder.
        if ($excludeScheduleId === null && $load->isOverload() && ! $load->overload_approved) {
            $overloadUnits = $this->loads->getOverloadUnits(
                (float) $load->total_units, (float) $load->full_load_threshold
            );
            $warnings[] = "This faculty has an overload of {$overloadUnits} units pending Campus Director approval.";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Block if the proposed time overlaps with the section's recess or lunch break.
     */
    private function checkBreakTimeConflict(Section $section, string $start, string $end): ?string
    {
        if ($section->recess_start && $section->recess_end) {
            if ($this->conflicts->timesOverlap($start, $end, $section->recess_start, $section->recess_end)) {
                return "Schedule ({$start}–{$end}) overlaps with the section's recess break ({$section->recess_start}–{$section->recess_end}).";
            }
        }

        if ($section->lunch_start && $section->lunch_end) {
            if ($this->conflicts->timesOverlap($start, $end, $section->lunch_start, $section->lunch_end)) {
                return "Schedule ({$start}–{$end}) overlaps with the section's lunch break ({$section->lunch_start}–{$section->lunch_end}).";
            }
        }

        return null;
    }

    /**
     * Block if the proposed slot falls outside the teacher's official working
     * hours for that day. Falls back to the early shift (07:30-16:30) when no
     * TeacherOfficialTime record exists for that teacher+day.
     */
    private function checkOfficialTime(int $facultyId, string $day, string $start, string $end): ?string
    {
        $ot = TeacherOfficialTime::where('user_id', $facultyId)
            ->where('day_of_week', $day)
            ->first();

        if ($ot !== null) {
            $otStart = substr($ot->start_time, 0, 5);
            $otEnd   = substr($ot->end_time, 0, 5);
        } else {
            $otStart = SchedulingConstants::SHIFT_EARLY['start'];
            $otEnd   = SchedulingConstants::SHIFT_EARLY['end'];
        }

        if ($start < $otStart || $end > $otEnd) {
            return "Schedule ({$start}–{$end}) on {$day} is outside the teacher's official time ({$otStart}–{$otEnd}).";
        }

        return null;
    }

    /**
     * Warn (never block) when the proposed day diverges from the established
     * weekday pattern of the SAME faculty teaching the SAME subject to other
     * sections. DeterministicSchedulingService keeps AI-generated placements
     * aligned to this pattern (see its assignSubjectDays()/
     * resolveGroupDayPlan()); this surfaces the same expectation for manual
     * edits and swaps — via ClassScheduleController::validateSchedule()/
     * store()/update() and ClassScheduleSwapService::validateProposal(),
     * which all funnel through validate() — without forcing it, since a
     * genuine one-off override (e.g. dodging a room conflict) can be
     * legitimate. See ClassScheduleController::realign() for the one-click
     * fix this warning points to.
     */
    private function checkDayPatternConsistency(array $data, int|array|null $excludeScheduleId): ?string
    {
        if (empty($data['faculty_id']) || empty($data['subject_id']) || empty($data['section_id'])) {
            return null;
        }

        $siblingDays = $this->conflicts->findSiblingPatternDays(
            (int) $data['faculty_id'],
            (int) $data['subject_id'],
            (int) $data['academic_term_id'],
            (int) $data['section_id'],
            $excludeScheduleId
        );

        if ($siblingDays === [] || in_array($data['day_of_week'], $siblingDays, true)) {
            return null;
        }

        $subjectName = Subject::find($data['subject_id'])?->name ?? 'This subject';
        $dayList     = implode('/', $siblingDays);

        // "Day pattern:" is a stable prefix the frontend matches on to offer
        // the one-click realign action — keep it if this message ever changes.
        return "Day pattern: {$subjectName} lands on {$dayList} for this faculty's other sections, but this "
            . "section is on {$data['day_of_week']} instead. Sections usually stay on the same weekday(s) so the "
            . "pattern is easy to follow — realign unless this is an intentional exception.";
    }

    /**
     * Warn if a lab/specialized subject is assigned to a lecture room (or vice versa).
     */
    private function checkRoomTypeCompatibility(Subject $subject, Classroom $classroom): ?string
    {
        $labSubjectTypes = ['laboratory', 'lecture_lab'];
        $labRoomTypes    = ['laboratory', 'science_lab', 'physics_lab',
                            'chemistry_lab', 'biology_lab', 'mathematics_lab',
                            'ict_lab', 'language_lab'];

        $isLabSubject = in_array($subject->subject_type, $labSubjectTypes);
        $isLabRoom    = in_array($classroom->classroom_type, $labRoomTypes);

        if ($isLabSubject && ! $isLabRoom) {
            return "Subject '{$subject->name}' requires a laboratory room but '{$classroom->name}' is a lecture room.";
        }

        if (! $isLabSubject && $isLabRoom && $classroom->classroom_type !== 'lecture') {
            return "Subject '{$subject->name}' is a lecture subject but is assigned to lab '{$classroom->name}'. Consider using a lecture room.";
        }

        return null;
    }
}
