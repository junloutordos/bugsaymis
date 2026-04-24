<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;

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
     * @param int|null $excludeScheduleId  Existing schedule id to skip (updates)
     */
    public function validate(array $data, ?int $excludeScheduleId = null): array
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
        $loadCheck = $this->validateFacultyLoadLimits($data);
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

        return [
            'valid'       => empty($errors),
            'errors'      => $errors,
            'warnings'    => $warnings,
            'conflicts'   => $conflictResult,
            'load_check'  => $loadCheck,
            'hours_check' => $hoursCheck,
        ];
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

    private function validateFacultyLoadLimits(array $data): array
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

        // Overload warning
        if ($load->isOverload() && ! $load->overload_approved) {
            $overloadUnits = $this->loads->getOverloadUnits(
                (float) $load->total_units, (float) $load->full_load_threshold
            );
            $warnings[] = "Adding this schedule will result in an overload of {$overloadUnits} units. Campus Director approval is required.";
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
