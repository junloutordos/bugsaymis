<?php

namespace App\Services\StudentPortal;

use Illuminate\Support\Facades\DB;

class FormSubmissions
{
    public const PROFILE_SECTIONS = ['academic', 'activities', 'social', 'career', 'residence', 'health'];
    public const MEDICAL_SECTIONS = ['allergies', 'immunizations', 'medical_history', 'vitamins'];

    /**
     * Map of section => submitted_at for the given student and school year,
     * optionally limited to a set of sections.
     */
    public static function map(string $pisayID, ?int $schoolYearId, ?array $sections = null): array
    {
        if (! $schoolYearId) {
            return [];
        }

        $query = DB::table('student_form_submissions')
            ->where('pisaysystemID', $pisayID)
            ->where('school_year_id', $schoolYearId);

        if ($sections !== null) {
            $query->whereIn('section', $sections);
        }

        return $query->pluck('submitted_at', 'section')->toArray();
    }

    /**
     * Record that a section was submitted for the current school year.
     * No-op when no current school year is set.
     */
    public static function mark(string $pisayID, int $gradeLevel, string $section): void
    {
        $sy = DB::table('school_years')->where('is_current', 1)->first();

        if (! $sy) {
            return;
        }

        DB::table('student_form_submissions')->updateOrInsert(
            ['pisaysystemID' => $pisayID, 'school_year_id' => $sy->id, 'section' => $section],
            ['grade_level' => $gradeLevel, 'submitted_at' => now()]
        );
    }
}
