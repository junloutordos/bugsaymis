<?php

namespace App\Services\Sos;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves a display-friendly identity for whoever triggered an SOS alert —
 * shared by SosAlertController::serialize() (page load / refresh) and
 * SosAlertService::broadcastPayload() (realtime push), so both surfaces
 * show the same reporter name + grade/section for students.
 */
class ReporterIdentityService
{
    /** @return array{name:string,type:string,grade_level:?int,section:?string} */
    public function resolve(?Model $triggerable): array
    {
        if (! $triggerable) {
            return ['name' => 'Unknown', 'type' => 'unknown', 'grade_level' => null, 'section' => null];
        }

        if ($triggerable instanceof User) {
            return ['name' => $triggerable->name, 'type' => 'user', 'grade_level' => null, 'section' => null];
        }

        if ($triggerable instanceof Student) {
            $name = trim($triggerable->firstname.' '.$triggerable->lastname);
            $currentSchoolYearId = SchoolYear::where('is_current', true)->first()?->id;

            $enrollment = $currentSchoolYearId
                ? StudentEnrollment::where('student_id', $triggerable->id)
                    ->where('school_year_id', $currentSchoolYearId)
                    ->active()
                    ->first()
                : null;

            $section = $enrollment?->section_id ? Section::find($enrollment->section_id) : null;

            return [
                'name'        => $name,
                'type'        => 'student',
                'grade_level' => $enrollment?->grade_level,
                'section'     => $section?->sectionname,
            ];
        }

        return ['name' => trim(($triggerable->firstname ?? '').' '.($triggerable->lastname ?? '')) ?: 'Unknown', 'type' => 'unknown', 'grade_level' => null, 'section' => null];
    }
}
