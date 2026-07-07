<?php

namespace App\Services\StudentPortal;

use Illuminate\Support\Facades\DB;

class GradeLevel
{
    /**
     * Compute a student's current grade level from their batch year:
     * grade = 12 − (batch − syEndYear), clamped to 7–12.
     * Returns 0 when no current school year is set.
     */
    public static function compute(int $batch): int
    {
        $sy = DB::table('school_years')->where('is_current', 1)->first();

        if (! $sy) {
            return 0;
        }

        // "2026-2027" → end year = 2027
        $syEndYear = (int) explode('-', $sy->name)[1];

        return max(7, min(12, 12 - ($batch - $syEndYear)));
    }
}
