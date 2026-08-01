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
            ->orderBy('start_time')
            ->get()
            ->groupBy('section_id');

        $grades = [];
        foreach (range(7, 12) as $gradeLevel) {
            $gradeSections = [];
            $classSlots = SchedulingConstants::getClassSlots($gradeLevel, $day);

            foreach ($sections->where('levelid', $gradeLevel) as $section) {
                $entries = ($scheduleRows->get($section->id) ?? collect())
                    ->map(function (ClassSchedule $schedule) use ($classSlots, $classDuration, $shift) {
                        $entry = $schedule->toCalendarArray();
                        $entry['start_time'] = $this->transformTime((string) $schedule->start_time, $classSlots, $classDuration, $shift);
                        $entry['end_time'] = $this->transformTime((string) $schedule->end_time, $classSlots, $classDuration, $shift);

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
                        'start' => $this->transformTime((string) $band['start'], $classSlots, $classDuration, $shift),
                        'end' => $this->transformTime((string) $band['end'], $classSlots, $classDuration, $shift),
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

            $this->assertNoGeneratedConflicts($grades);
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
     * Compress each completed canonical class period to the requested duration,
     * then apply an optional campus-wide shift (the transferred flag ceremony).
     */
    private function transformTime(string $time, array $classSlots, ?int $classDuration, int $shift): string
    {
        $sourceMinutes = SchedulingConstants::toMinutes(substr($time, 0, 5));
        $minutes = $sourceMinutes;

        if ($classDuration !== null) {
            foreach ($classSlots as $slot) {
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

    /** Ensure campus-wide compression does not create new faculty or room overlaps. */
    private function assertNoGeneratedConflicts(array $grades): void
    {
        $entries = collect($grades)
            ->flatMap(fn (array $grade) => $grade['sections'])
            ->flatMap(fn (array $section) => $section['entries'])
            ->values();

        foreach (['faculty' => 'faculty', 'classroom' => 'room'] as $relation => $label) {
            $groups = $entries->filter(fn (array $entry) => isset($entry[$relation]['id']))
                ->groupBy(fn (array $entry) => $entry[$relation]['id']);

            foreach ($groups as $rows) {
                $sorted = $rows->sortBy('start_time')->values();
                for ($index = 1; $index < $sorted->count(); $index++) {
                    if ($sorted[$index]['start_time'] < $sorted[$index - 1]['end_time']) {
                        throw ValidationException::withMessages([
                            'activity_start_time' => "The compressed timetable creates a {$label} conflict. Review the preview or choose another activity time.",
                        ]);
                    }
                }
            }
        }
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
