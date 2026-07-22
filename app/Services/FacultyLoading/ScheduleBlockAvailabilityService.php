<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Section;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScheduleBlockAvailabilityService
{
    private const SECTION_COLUMNS = [
        'lunch' => Section::LUNCH_OVERRIDE_COLUMNS,
        'recess' => Section::RECESS_OVERRIDE_COLUMNS,
        'white_space' => Section::WHITE_SPACE_OVERRIDE_COLUMNS,
        'wellness' => Section::WELLNESS_OVERRIDE_COLUMNS,
    ];

    private const GRADE_SETTING_KEYS = [
        'consultation' => 'CONSULTATION_BY_GRADE_DAY',
        'lunch' => 'LUNCH_BY_GRADE_DAY',
        'recess' => 'RECESS_BY_GRADE_DAY',
        'white_space' => 'WHITE_SPACE_BY_GRADE',
        'wellness' => 'WELLNESS_BY_GRADE',
    ];

    /**
     * @return Collection<int,Section>
     */
    public function affectedSections(
        int $termId,
        string $scope,
        string $blockType,
        string $day,
        ?int $grade = null,
        ?int $sectionId = null,
    ): Collection {
        $term = AcademicTerm::findOrFail($termId);

        $query = Section::query()
            ->where('school_year_id', $term->school_year_id)
            ->where('is_active', true);

        if ($scope === 'section') {
            return $query->whereKey($sectionId)->get();
        }

        if ($scope === 'grade') {
            $query->where('levelid', $grade);
        }

        $sections = $query->get();

        // A broader setting never changes sections with their own override.
        $sections = $sections->reject(fn (Section $section) => $this->hasSectionOverride($section, $blockType, $day));

        if ($scope === 'campus') {
            $gradeOverrides = SchedulingConstants::setting(self::GRADE_SETTING_KEYS[$blockType] ?? '') ?? [];
            $sections = $sections->reject(
                fn (Section $section) => isset($gradeOverrides[(int) $section->levelid][$day])
            );
        }

        return $sections->values();
    }

    public function assertAvailable(Collection $sections, int $termId, string $day, ?array $window): void
    {
        if ($window === null || $sections->isEmpty()) {
            return;
        }

        $conflict = ClassSchedule::query()
            ->with(['section:id,sectionname,levelid', 'subject:id,code,name'])
            ->occupying()
            ->where('academic_term_id', $termId)
            ->whereIn('section_id', $sections->pluck('id'))
            ->where('day_of_week', $day)
            ->where('start_time', '<', $window['end'])
            ->where('end_time', '>', $window['start'])
            ->orderBy('start_time')
            ->first();

        if (! $conflict) {
            return;
        }

        $section = $conflict->section
            ? "Grade {$conflict->section->levelid} {$conflict->section->sectionname}"
            : 'an affected section';
        $label = $conflict->subject?->code
            ?? $conflict->subject?->name
            ?? $conflict->title
            ?? 'an existing schedule';
        $start = substr((string) $conflict->start_time, 0, 5);
        $end = substr((string) $conflict->end_time, 0, 5);

        throw ValidationException::withMessages([
            'time' => "Cannot apply this block because {$section} has {$label} plotted on {$day} from {$start} to {$end}.",
        ]);
    }

    private function hasSectionOverride(Section $section, string $blockType, string $day): bool
    {
        if ($blockType === 'consultation') {
            return $section->consultationOverrides()->where('day_of_week', $day)->exists();
        }

        [$startColumn, $endColumn] = self::SECTION_COLUMNS[$blockType][$day] ?? [null, null];

        return $startColumn && $section->$startColumn && $section->$endColumn;
    }
}
