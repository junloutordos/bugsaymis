<?php

namespace App\Services\Sos;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LocationResolverService
{
    /** @return array{type:string,label:string,building:?string,room:?string,source:string} */
    public function resolve(Model $triggerable, Carbon $atTime): array
    {
        return $triggerable instanceof Student
            ? $this->resolveStudent($triggerable, $atTime)
            : $this->resolveEmployee($triggerable, $atTime);
    }

    private function resolveStudent(Student $student, Carbon $atTime): array
    {
        $term = $this->currentTerm();
        if (! $term) {
            return $this->unknown();
        }

        $enrollment = StudentEnrollment::where('student_id', $student->id)
            ->where('school_year_id', $term->school_year_id)
            ->active()
            ->first();

        if (! $enrollment) {
            return $this->unknown('Not enrolled this term');
        }

        $section = Section::find($enrollment->section_id);
        if (! $section) {
            return $this->unknown('Not enrolled this term');
        }

        $entry = $this->matchScheduleEntry($term, $atTime, sectionId: $section->id, facultyId: null);
        if ($entry) {
            return [
                'type' => 'classroom',
                'label' => "{$entry['classroom']} — {$entry['subject']} with {$entry['faculty']}",
                'building' => $entry['building'],
                'room' => $entry['classroom'],
                'source' => 'schedule',
            ];
        }

        if ($section->classroom) {
            return [
                'type' => 'homeroom',
                'label' => "Homeroom: {$section->classroom->name}",
                'building' => $section->classroom->building,
                'room' => $section->classroom->name,
                'source' => 'homeroom',
            ];
        }

        return $this->unknown();
    }

    private function resolveEmployee(User $user, Carbon $atTime): array
    {
        $term = $this->currentTerm();

        if ($term) {
            $entry = $this->matchScheduleEntry($term, $atTime, sectionId: null, facultyId: $user->id);
            if ($entry) {
                return [
                    'type' => 'classroom',
                    'label' => "Teaching {$entry['subject']} — {$entry['classroom']}",
                    'building' => $entry['building'],
                    'room' => $entry['classroom'],
                    'source' => 'schedule',
                ];
            }
        }

        if ($user->office_id && $user->office) {
            $label = $user->office->division
                ? "{$user->office->name} ({$user->office->division->division_name})"
                : $user->office->name;

            return [
                'type' => 'office',
                'label' => $label,
                'building' => null,
                'room' => $user->office->name,
                'source' => 'office',
            ];
        }

        return $this->unknown();
    }

    protected function currentTerm(): ?AcademicTerm
    {
        return SchoolYear::current()->first()?->currentTerm();
    }

    /** @return array{classroom:?string,building:?string,subject:?string,faculty:?string}|null */
    protected function matchScheduleEntry(AcademicTerm $term, Carbon $atTime, ?int $sectionId, ?int $facultyId): ?array
    {
        $query = ClassSchedule::with(['classroom', 'subject', 'faculty'])
            ->classes()
            ->occupying()
            ->onDay($atTime->englishDayOfWeek)
            ->where('academic_term_id', $term->id)
            ->where('start_time', '<=', $atTime->format('H:i:s'))
            ->where('end_time', '>', $atTime->format('H:i:s'));

        $schedule = $sectionId !== null
            ? $query->where('section_id', $sectionId)->first()
            : $query->forFaculty($facultyId)->first();

        if (! $schedule) {
            return null;
        }

        return [
            'classroom' => $schedule->classroom?->name,
            'building' => $schedule->classroom?->building,
            'subject' => $schedule->subject?->name,
            'faculty' => $schedule->faculty?->name,
        ];
    }

    protected function unknown(string $reason = 'No scheduled location'): array
    {
        return ['type' => 'unknown', 'label' => $reason, 'building' => null, 'room' => null, 'source' => 'fallback'];
    }
}
