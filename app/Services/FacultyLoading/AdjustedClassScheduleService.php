<?php

namespace App\Services\FacultyLoading;

use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\FacultyLoading\Section;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdjustedClassScheduleService
{
    public function __construct(private readonly ScienceCoreService $scienceCore) {}

    /**
     * Build the complete campus schedule for one exceptional date without
     * mutating the underlying weekly schedule or bell-schedule configuration.
     *
     * @return array<string,mixed>
     */
    public function generate(ClassScheduleDayAdjustment $adjustment): array
    {
        $adjustment->loadMissing('academicTerm.schoolYear');

        $term = $adjustment->academicTerm;
        $day = Carbon::parse($adjustment->effective_date)->englishDayOfWeek;
        $hasFlag = $adjustment->hasFlagCeremony();
        $hasShortenedClasses = $adjustment->hasShortenedClasses();
        $stemSplit = $adjustment->isEarlyStartStemSplit();
        $shift = $hasFlag ? (int) $adjustment->shift_minutes : 0;
        $classDuration = $hasShortenedClasses ? (int) ($adjustment->class_duration_minutes ?: 30) : null;
        $stemMinutes = $stemSplit ? (int) ($adjustment->stem_class_duration_minutes ?: 50) : null;
        $nonStemMinutes = $stemSplit ? (int) ($adjustment->non_stem_class_duration_minutes ?: 30) : null;
        $dayStartMinutes = $stemSplit
            ? SchedulingConstants::toMinutes(substr((string) ($adjustment->day_start_time ?: '07:00'), 0, 5))
            : null;
        $activityStart = $hasShortenedClasses ? substr((string) $adjustment->activity_start_time, 0, 5) : null;
        $activityEnd = $hasShortenedClasses ? substr((string) $adjustment->activity_end_time, 0, 5) : null;
        $protectedPairs = $adjustment->protectsAssessmentPeriods()
            ? $this->majorAssessmentPairs((int) $term->school_year_id, $adjustment->effective_date->toDateString())
            : null;
        $selectedGrades = $adjustment->gradeLevels();
        $overridesByScheduleId = $adjustment->exists
            ? $adjustment->overrides()->get()->keyBy('class_schedule_id')
            : collect();
        $bandOverridesBySectionType = $adjustment->exists
            ? $adjustment->bandOverrides()->get()->keyBy(fn ($o) => "{$o->section_id}:{$o->band_type}")
            : collect();

        $overrideColumns = array_merge(
            ...array_values(Section::LUNCH_OVERRIDE_COLUMNS),
            ...array_values(Section::RECESS_OVERRIDE_COLUMNS),
            ...array_values(Section::WHITE_SPACE_OVERRIDE_COLUMNS),
            ...array_values(Section::WELLNESS_OVERRIDE_COLUMNS),
        );

        $sections = Section::where('school_year_id', $term->school_year_id)
            ->where('is_active', true)
            ->whereBetween('levelid', [7, 12])
            ->where('sectionname', 'not like', 'ELEC-%')
            ->where('sectionname', 'not like', ScienceCoreService::SECTION_PREFIX.'%')
            ->with('consultationOverrides')
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get(array_values(array_unique(array_merge(
                ['id', 'sectionname', 'levelid', 'school_year_id'],
                $overrideColumns,
            ))));

        $scheduleRows = ClassSchedule::with(['subject', 'classroom', 'faculty:id,name', 'section:id,sectionname,levelid'])
            ->where('academic_term_id', $term->id)
            ->where('day_of_week', $day)
            ->whereIn('section_id', $sections->pluck('id'))
            ->occupying()
            ->classes()
            ->orderBy('start_time')
            ->get()
            ->groupBy('section_id');

        $grades = [];
        foreach (range(7, 12) as $gradeLevel) {
            if (! in_array($gradeLevel, $selectedGrades, true)) {
                // Not part of this adjustment — the regular weekly schedule
                // applies unchanged on this date for this grade.
                $grades[] = [
                    'grade_level' => $gradeLevel,
                    'regular_schedule_applies' => true,
                    'sections' => [],
                ];

                continue;
            }

            $gradeSections = [];

            foreach ($sections->where('levelid', $gradeLevel) as $section) {
                $sectionSchedule = $scheduleRows->get($section->id) ?? collect();

                // For early-start STEM-split, every section is individually
                // anchored so its own first class period starts at the same
                // campus-wide day_start_time — different grades' sections
                // normally start their first period at different clock
                // times, so the shift needed to reach the same target start
                // differs per section.
                $sectionShift = $shift;
                if ($stemSplit && $sectionSchedule->isNotEmpty()) {
                    $firstOriginalStart = SchedulingConstants::toMinutes(
                        substr((string) $sectionSchedule->first()->start_time, 0, 5)
                    );
                    $sectionShift = $dayStartMinutes - $firstOriginalStart;
                }

                // Compression is measured against this section's OWN actual
                // scheduled times, not the idealized bell-schedule grid — real
                // timetables routinely drift from the canonical periods (see
                // test_same_section_period_drift_does_not_false_positive_room_conflict).
                // Anchoring to the canonical grid instead of reality let classes
                // compress to the wrong — sometimes zero — duration whenever a
                // section's actual periods didn't tile it exactly.
                $sectionSlots = $sectionSchedule
                    ->map(function (ClassSchedule $s) use ($classDuration, $protectedPairs, $stemSplit, $stemMinutes, $nonStemMinutes) {
                        $isProtected = $protectedPairs && $s->subject_id
                            && $protectedPairs->has("{$s->section_id}:{$s->subject_id}");

                        $target = $stemSplit
                            ? ($s->subject?->is_stem ? $stemMinutes : $nonStemMinutes)
                            : $classDuration;

                        return [
                            'start' => substr((string) $s->start_time, 0, 5),
                            'end' => substr((string) $s->end_time, 0, 5),
                            // null = this period keeps its original length (not
                            // compressed): either the day isn't shortened, or this
                            // period is protected by a major assessment plotted today.
                            'target' => $isProtected ? null : $target,
                        ];
                    })
                    ->values()
                    ->all();

                if ($stemSplit) {
                    // Lunch must actually disappear from the timetable for this
                    // type, not just from the display — see lunchGapSlots().
                    $sectionSlots = array_merge(
                        $sectionSlots,
                        $this->lunchGapSlots($sectionSchedule, $gradeLevel, $day, $section)
                    );
                }

                $entries = $sectionSchedule
                    ->map(function (ClassSchedule $schedule) use ($sectionSlots, $sectionShift, $overridesByScheduleId) {
                        $entry = $schedule->toCalendarArray();
                        $entry['raw_start_time'] = substr((string) $schedule->start_time, 0, 5);
                        $entry['raw_end_time'] = substr((string) $schedule->end_time, 0, 5);

                        $override = $overridesByScheduleId->get($schedule->id);
                        if ($override) {
                            // A manual time-only correction takes precedence
                            // over the computed compression/shift for this
                            // one entry — used to resolve a flagged conflict
                            // before publishing. Flagged for audit display.
                            $entry['start_time'] = substr((string) $override->override_start_time, 0, 5);
                            $entry['end_time'] = substr((string) $override->override_end_time, 0, 5);
                            $entry['manually_adjusted'] = true;
                        } else {
                            $entry['start_time'] = $this->transformTime((string) $schedule->start_time, $sectionSlots, $sectionShift);
                            $entry['end_time'] = $this->transformTime((string) $schedule->end_time, $sectionSlots, $sectionShift);
                            $entry['manually_adjusted'] = false;
                        }

                        return $entry;
                    })
                    ->values()
                    ->all();

                $bands = SchedulingConstants::getDisplayBlockedSlots(
                    $gradeLevel,
                    $day,
                    $this->trimWindow($section->lunchOverrideFor($day)),
                    $this->trimWindow($section->recessOverrideFor($day)),
                    $this->trimWindow($section->whiteSpaceOverrideFor($day)),
                    $this->trimWindow($section->wellnessOverrideFor($day)),
                    $this->trimWindow($section->consultationOverrideFor($day)),
                );

                foreach (SchedulingConstants::getElectiveWindows($gradeLevel, $day) as $band) {
                    $bands[] = [...$band, 'type' => 'ELECTIVE'];
                }

                foreach ($this->scienceCore->getScienceCoreWindows(
                    (int) $term->school_year_id,
                    (int) $term->id,
                    $gradeLevel,
                    $day,
                ) as $band) {
                    $bands[] = [...$band, 'type' => 'SCIENCE_CORE'];
                }

                $rejectedBandTypes = $stemSplit
                    ? ['CONSULT', 'ACTIVITY', 'FLAG_RETREAT', 'LUNCH']
                    : ['CONSULT', 'ACTIVITY', 'FLAG_RETREAT'];

                $bands = collect($bands)
                    ->when($hasShortenedClasses, fn ($items) => $items->reject(
                        fn (array $band) => in_array($band['type'] ?? '', $rejectedBandTypes, true),
                    ))
                    ->map(fn (array $band) => [
                        ...$band,
                        'start' => $this->transformTime((string) $band['start'], $sectionSlots, $sectionShift),
                        'end' => $this->transformTime((string) $band['end'], $sectionSlots, $sectionShift),
                    ])
                    ->when($activityStart, fn ($items) => $items->filter(
                        fn (array $band) => $band['end'] <= $activityStart,
                    ))
                    ->sortBy('start')
                    ->values()
                    ->map(function (array $band) use ($section, $bandOverridesBySectionType) {
                        if (! in_array($band['type'] ?? '', ['RECESS', 'WHITE_SPACE', 'WELLNESS'], true)) {
                            return $band;
                        }

                        $override = $bandOverridesBySectionType->get("{$section->id}:{$band['type']}");
                        if (! $override) {
                            return [...$band, 'manually_adjusted' => false];
                        }

                        return [
                            ...$band,
                            'start' => substr((string) $override->override_start_time, 0, 5),
                            'end' => substr((string) $override->override_end_time, 0, 5),
                            'manually_adjusted' => true,
                        ];
                    })
                    ->all();

                if ($activityStart && $activityEnd) {
                    $bands[] = [
                        'start' => $activityStart,
                        'end' => $activityEnd,
                        'type' => 'OFFICIAL_ACTIVITY',
                        'label' => $adjustment->activity_title,
                    ];
                }

                if ($adjustment->hasHealthBreak()) {
                    $bands[] = [
                        'start' => substr((string) $adjustment->health_break_start_time, 0, 5),
                        'end' => substr((string) $adjustment->health_break_end_time, 0, 5),
                        'type' => 'HEALTH_BREAK',
                        'label' => $adjustment->health_break_title,
                        // Unlike Recess/White Space/Wellness there's no
                        // campus default to fall back to — the band only
                        // ever exists once deliberately declared, so it's
                        // always treated as a manual, editable/draggable
                        // block (see upsertBandOverride()'s HEALTH_BREAK
                        // special case: it writes straight to the
                        // adjustment's own health_break_* columns, shared
                        // across every section, instead of a per-section
                        // override row).
                        'manually_adjusted' => true,
                    ];
                }

                $gradeSections[] = [
                    'id' => (int) $section->id,
                    'name' => $section->sectionname,
                    'entries' => $entries,
                    'bands' => $bands,
                ];
            }

            $grades[] = [
                'grade_level' => $gradeLevel,
                'sections' => $gradeSections,
            ];
        }

        $conflictWarnings = [];

        if ($activityStart) {
            $lateEntry = collect($grades)
                ->flatMap(fn (array $grade) => $grade['sections'])
                ->flatMap(fn (array $section) => $section['entries'])
                ->first(fn (array $entry) => $entry['end_time'] > $activityStart);

            if ($lateEntry) {
                $label = $lateEntry['subject']['code'] ?? $lateEntry['subject']['name'] ?? 'A class';
                $message = "{$label} still ends at {$lateEntry['end_time']}. Choose a later activity start time.";

                // For every other shortened-class type the Official Activity
                // is a required, deliberate reservation of that time slot, so
                // classes running into it is a real blocking problem. For
                // early_start_stem_split the Activity fields are optional
                // (see validatedData()) — treating an overrun as a hard error
                // here made generate() unconditionally throw, which broke
                // resolve()/print() (no exception handling on those GET
                // routes) and made publish() silently no-op, with no way to
                // even reach the Resolve Conflicts screen meant to fix it.
                // Downgraded to the same reviewable warning already used for
                // compression-only overlaps below.
                if ($stemSplit) {
                    $conflictWarnings[] = $message;
                } else {
                    throw ValidationException::withMessages([
                        'activity_start_time' => $message,
                    ]);
                }
            }
        }

        // Conflict detection always runs for any shortened-family day, not
        // just when an Official Activity is declared — a plain early-start
        // day with no activity and no health break previously skipped this
        // check entirely.
        if ($hasShortenedClasses) {
            $conflictWarnings = array_merge($conflictWarnings, $this->assertNoGeneratedConflicts($grades));
        }

        return [
            'effective_date' => $adjustment->effective_date->toDateString(),
            'weekday' => $day,
            'adjustment_type' => $adjustment->adjustment_type,
            'has_flag_ceremony' => $hasFlag,
            'has_shortened_classes' => $hasShortenedClasses,
            'class_duration_minutes' => $classDuration,
            'ceremony' => $hasFlag ? [
                'start' => substr((string) $adjustment->ceremony_start_time, 0, 5),
                'end' => substr((string) $adjustment->ceremony_end_time, 0, 5),
                'label' => 'Flag Ceremony',
            ] : null,
            'activity' => $hasShortenedClasses ? [
                'title' => $adjustment->activity_title,
                'start' => $activityStart,
                'end' => $activityEnd,
            ] : null,
            'calendar_start' => $stemSplit ? substr((string) ($adjustment->day_start_time ?: '07:00'), 0, 5) : '07:30',
            'calendar_end' => '17:00',
            'conflict_warnings' => $conflictWarnings,
            'grades' => $grades,
        ];
    }

    /** Published adjustments always render from their frozen official copy. */
    public function printableSnapshot(ClassScheduleDayAdjustment $adjustment): array
    {
        return $adjustment->isPublished() && $adjustment->schedule_snapshot
            ? $adjustment->schedule_snapshot
            : $this->generate($adjustment);
    }

    /**
     * Compress each of this section's own already-completed class periods
     * down to its own target duration, then apply an optional campus-wide
     * shift (the transferred flag ceremony).
     *
     * $slots are this section's own actual scheduled class times for the
     * day — not the idealized canonical bell-schedule grid — so the savings
     * reflect what this section's real timetable actually did, regardless of
     * how it may drift from the campus-wide canonical periods. Each slot
     * carries its own 'target' duration (null = not compressed, e.g. an
     * un-shortened day, or a period protected by a scheduled major
     * assessment) so a single day can mix compressed and protected periods.
     */
    private function transformTime(string $time, array $slots, int $shift): string
    {
        $sourceMinutes = SchedulingConstants::toMinutes(substr($time, 0, 5));
        $minutes = $sourceMinutes;

        foreach ($slots as $slot) {
            if ($slot['target'] === null) {
                continue;
            }

            $slotEnd = SchedulingConstants::toMinutes($slot['end']);
            if ($slotEnd > $sourceMinutes) {
                continue;
            }

            $slotMinutes = $slotEnd - SchedulingConstants::toMinutes($slot['start']);
            $minutes -= max(0, $slotMinutes - $slot['target']);
        }

        return SchedulingConstants::fromMinutes($minutes + $shift);
    }

    /**
     * Synthetic zero-target slots for the portion of the campus Lunch window
     * that genuinely falls between two of this section's own consecutive
     * class periods — used only for early_start_stem_split, where Lunch is
     * meant to disappear from the timetable, not just from the display.
     * Only the intersection of the real gap and the canonical Lunch window
     * collapses: a section with no real gap there loses nothing, and a
     * section whose gap only partially overlaps only loses that portion —
     * same reasoning as anchoring compression to real data instead of the
     * idealized bell-schedule grid (see this class's other docblocks).
     *
     * @return array<int,array{start:string,end:string,target:int}>
     */
    private function lunchGapSlots(Collection $sectionSchedule, int $gradeLevel, string $day, Section $section): array
    {
        $lunch = SchedulingConstants::getEffectiveLunch($gradeLevel, $day, $this->trimWindow($section->lunchOverrideFor($day)));
        $lunchStart = SchedulingConstants::toMinutes($lunch['start']);
        $lunchEnd = SchedulingConstants::toMinutes($lunch['end']);

        $ordered = $sectionSchedule->values();
        $slots = [];

        for ($index = 1; $index < $ordered->count(); $index++) {
            $gapStart = SchedulingConstants::toMinutes(substr((string) $ordered[$index - 1]->end_time, 0, 5));
            $gapEnd = SchedulingConstants::toMinutes(substr((string) $ordered[$index]->start_time, 0, 5));

            $overlapStart = max($gapStart, $lunchStart);
            $overlapEnd = min($gapEnd, $lunchEnd);

            if ($overlapEnd > $overlapStart) {
                $slots[] = [
                    'start' => SchedulingConstants::fromMinutes($overlapStart),
                    'end' => SchedulingConstants::fromMinutes($overlapEnd),
                    'target' => 0,
                ];
            }
        }

        return $slots;
    }

    /**
     * {section_id}:{subject_id} pairs with a MAJOR assessment plotted on
     * $date this school year — the periods that must keep their original
     * length under the "protect assessments" adjustment type. Formative/
     * alternative/ILA assessments do not protect a period, only long tests
     * (is_major = true). Joins class_record_assessment_dates too, since a
     * multi-date assessment's activity_date column only mirrors its primary
     * date. Uses each grading category's own subject override (PEHM-style
     * co-taught records) falling back to the class record's subject.
     */
    private function majorAssessmentPairs(int $schoolYearId, string $date): Collection
    {
        return ClassRecordAssessment::schoolYearScopeQuery($schoolYearId)
            ->leftJoin('class_record_assessment_dates as crad', 'crad.class_record_assessment_id', '=', 'class_record_assessments.id')
            ->leftJoin('grading_categories as gc', 'gc.id', '=', 'class_record_assessments.grading_category_id')
            ->where('class_record_assessments.is_major', true)
            ->where(fn ($query) => $query
                ->where('class_record_assessments.activity_date', $date)
                ->orWhere('crad.activity_date', $date))
            ->selectRaw('cr.section_id as section_id, COALESCE(gc.subject_id, cr.subject_id) as subject_id')
            ->distinct()
            ->get()
            ->filter(fn ($row) => $row->section_id && $row->subject_id)
            ->mapWithKeys(fn ($row) => ["{$row->section_id}:{$row->subject_id}" => true]);
    }

    /**
     * An overlap after compression is only a genuine conflict if the two
     * bookings ALSO overlapped at their original, uncompressed times — a
     * real pre-existing double-booking, independent of compression, which
     * still blocks the save regardless of grade.
     *
     * Compression can fabricate an overlap between two originally
     * non-overlapping bookings even within the same section or grade: real
     * section timetables drift from the idealized canonical bell-schedule
     * grid by different amounts (different sections bank different
     * compression savings by the same wall-clock moment), so two genuinely
     * sequential, non-conflicting bookings can appear to invert order after
     * independent per-entry compression. Those are downgraded to a warning
     * instead of a blocking error.
     *
     * @return array<int,string> warning messages for compression-only overlaps
     */
    private function assertNoGeneratedConflicts(array $grades): array
    {
        $entries = collect($grades)
            ->flatMap(fn (array $grade) => $grade['sections'])
            ->flatMap(fn (array $section) => $section['entries'])
            ->values();

        $warnings = [];

        foreach (['faculty' => 'faculty', 'classroom' => 'room'] as $relation => $label) {
            $groups = $entries->filter(fn (array $entry) => isset($entry[$relation]['id']))
                ->groupBy(fn (array $entry) => $entry[$relation]['id']);

            foreach ($groups as $rows) {
                $sorted = $rows->sortBy('start_time')->values();

                for ($index = 1; $index < $sorted->count(); $index++) {
                    $previous = $sorted[$index - 1];
                    $current = $sorted[$index];

                    if ($current['start_time'] >= $previous['end_time']) {
                        continue;
                    }

                    if ($current['raw_start_time'] < $previous['raw_end_time']
                        && $previous['raw_start_time'] < $current['raw_end_time']) {
                        throw ValidationException::withMessages([
                            'activity_start_time' => "The compressed timetable creates a {$label} conflict. Review the preview or choose another activity time.",
                        ]);
                    }

                    $name = $current[$relation]['name'] ?? $current[$relation]['code'] ?? "#{$current[$relation]['id']}";
                    $warnings[] = sprintf(
                        'Possible %s overlap for %s between Grade %s and Grade %s around %s. Review the preview before publishing.',
                        $label,
                        $name,
                        $previous['grade_level'],
                        $current['grade_level'],
                        $current['start_time'],
                    );
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    /** @return array{start:string,end:string}|null */
    private function trimWindow(?array $window): ?array
    {
        if (! $window) {
            return null;
        }

        return [
            'start' => substr((string) $window['start'], 0, 5),
            'end' => substr((string) $window['end'], 0, 5),
        ];
    }
}
