<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetEnrollmentStatusBreakdownTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetEnrollmentStatusBreakdownToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_enrollment_status_counts(): void
    {
        // student_enrollments requires school_year_id, section_id, grade_level (tinyint,
        // not a label string), and enrollment_date — confirmed via
        // database/migrations/*_create_student_enrollments_table.php. Unique on
        // [student_id, school_year_id], so distinct student_id per row below.
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);

        StudentEnrollment::create(['student_id' => 1, 'school_year_id' => $schoolYear->id, 'section_id' => 1, 'grade_level' => 7, 'status' => 'enrolled', 'enrollment_date' => now()]);
        StudentEnrollment::create(['student_id' => 2, 'school_year_id' => $schoolYear->id, 'section_id' => 1, 'grade_level' => 7, 'status' => 'enrolled', 'enrollment_date' => now()]);
        StudentEnrollment::create(['student_id' => 3, 'school_year_id' => $schoolYear->id, 'section_id' => 2, 'grade_level' => 8, 'status' => 'transferred_out', 'enrollment_date' => now()]);

        $user = User::factory()->create();

        $result = (new GetEnrollmentStatusBreakdownTool())->execute($user, []);

        $this->assertEquals(['enrolled' => 2, 'transferred_out' => 1], $result);
    }
}
