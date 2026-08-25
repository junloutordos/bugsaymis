<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\FacultyLoading\Section;
use Carbon\Carbon;
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
        $shift = $hasFlag ? (int) $adjustment->shift_minutes : 0;
        $classDuration = $hasShortenedClasses ? (int) ($adjustment->class_duration_minutes ?: 30) : null;
        $activityStart = $hasShortenedClasses ? substr((string) $adjustment->activity_start_time, 0, 5) : null;
        $activityEnd = $hasShortenedClasses ? substr((string) $adjustment->activity_end_time, 0, 5) : null;

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
            $gradeSections = [];

            foreach ($sections->where('levelid', $gradeLevel) as $section) {
                $sectionSchedule = $scheduleRows->get($section->id) ?? collect();

                // Compression is measured against this section's OWN actual
                // scheduled times, not the idealized bell-schedule grid — real
                // timetables routinely drift from the canonical periods (see
                // test_same_section_period_drift_does_not_false_positive_room_conflict).
                // Anchoring to the canonical grid instead of reality let classes
                // compress to the wrong — sometimes zero — duration whenever a
                // section's actual periods didn't tile it exactly.
                $sectionSlots = $sectionSchedule
                    ->map(fn (ClassSchedule $s) => [
                        'start' => substr((string) $s->start_time, 0, 5),
                        'end' => substr((string) $s->end_time, 0, 5),
                    ])
                    ->values()
                    ->all();

                $entries = $sectionSchedule
                    ->map(function (ClassSchedule $schedule) use ($sectionSlots, $classDuration, $shift) {
                        $entry = $schedule->toCalendarArray();
                        $entry['raw_start_time'] = substr((string) $schedule->start_time, 0, 5);
                        $entry['raw_end_time'] = substr((string) $schedule->end_time, 0, 5);
                        $entry['start_time'] = $this->transformTime((string) $schedule->start_time, $sectionSlots, $classDuration, $shift);
                        $entry['end_time'] = $this->transformTime((string) $schedule->end_time, $sectionSlots, $classDuration, $shift);

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

                $bands = collect($bands)
                    ->when($hasShortenedClasses, fn ($items) => $items->reject(
                        fn (array $band) => in_array($band['type'] ?? '', ['CONSULT', 'ACTIVITY', 'FLAG_RETREAT'], true),
                    ))
                    ->map(fn (array $band) => [
                        ...$band,
                        'start' => $this->transformTime((string) $band['start'], $sectionSlots, $classDuration, $shift),
                        'end' => $this->transformTime((string) $band['end'], $sectionSlots, $classDuration, $shift),
                    ])
                    ->when($activityStart, fn ($items) => $items->filter(
                        fn (array $band) => $band['end'] <= $activityStart,
                    ))
                    ->sortBy('start')
                    ->values()
                    ->all();

                if ($activityStart && $activityEnd) {
                    $bands[] = [
                        'start' => $activityStart,
                        'end' => $activityEnd,
                        'type' => 'OFFICIAL_ACTIVITY',
                        'label' => $adjustment->activity_title,
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
                throw ValidationException::withMessages([
                    'activity_start_time' => "{$label} still ends at {$lateEntry['end_time']}. Choose a later activity start time.",
                ]);
            }

            $conflictWarnings = $this->assertNoGeneratedConflicts($grades);
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
            'calendar_start' => '07:30',
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
     * down to the requested duration, then apply an optional campus-wide
     * shift (the transferred flag ceremony).
     *
     * $slots are this section's own actual scheduled class times for the
     * day — not the idealized canonical bell-schedule grid — so the savings
     * reflect what this section's real timetable actually did, regardless of
     * how it may drift from the campus-wide canonical periods.
     */
    private function transformTime(string $time, array $slots, ?int $classDuration, int $shift): string
    {
        $sourceMinutes = SchedulingConstants::toMinutes(substr($time, 0, 5));
        $minutes = $sourceMinutes;

        if ($classDuration !== null) {
            foreach ($slots as $slot) {
                $slotEnd = SchedulingConstants::toMinutes($slot['end']);
                if ($slotEnd > $sourceMinutes) {
                    continue;
                }

                $slotMinutes = $slotEnd - SchedulingConstants::toMinutes($slot['start']);
                $minutes -= max(0, $slotMinutes - $classDuration);
            }
        }

        return SchedulingConstants::fromMinutes($minutes + $shift);
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
