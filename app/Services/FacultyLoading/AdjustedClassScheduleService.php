<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\FacultyLoading\Section;
use Carbon\Carbon;

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
        $shift = (int) $adjustment->shift_minutes;

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

            foreach ($sections->where('levelid', $gradeLevel) as $section) {
                $entries = ($scheduleRows->get($section->id) ?? collect())
                    ->map(function (ClassSchedule $schedule) use ($shift) {
                        $entry = $schedule->toCalendarArray();
                        $entry['start_time'] = $this->shiftTime((string) $schedule->start_time, $shift);
                        $entry['end_time'] = $this->shiftTime((string) $schedule->end_time, $shift);

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
                    ->map(fn (array $band) => [
                        ...$band,
                        'start' => $this->shiftTime((string) $band['start'], $shift),
                        'end' => $this->shiftTime((string) $band['end'], $shift),
                    ])
                    ->sortBy('start')
                    ->values()
                    ->all();

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

        $allEnds = collect($grades)
            ->flatMap(fn (array $grade) => $grade['sections'])
            ->flatMap(fn (array $section) => [
                ...array_column($section['entries'], 'end_time'),
                ...array_column($section['bands'], 'end'),
            ])
            ->filter();

        return [
            'effective_date' => $adjustment->effective_date->toDateString(),
            'weekday' => $day,
            'ceremony' => [
                'start' => substr((string) $adjustment->ceremony_start_time, 0, 5),
                'end' => substr((string) $adjustment->ceremony_end_time, 0, 5),
                'label' => 'Flag Ceremony',
            ],
            'calendar_start' => substr((string) $adjustment->ceremony_start_time, 0, 5),
            'calendar_end' => $allEnds->max() ?: $this->shiftTime('17:00', $shift),
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

    private function shiftTime(string $time, int $minutes): string
    {
        return Carbon::createFromFormat(strlen($time) > 5 ? 'H:i:s' : 'H:i', $time)
            ->addMinutes($minutes)
            ->format('H:i');
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
