<?php

namespace Tests\Feature;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\PM2\EmployeeIpcrV2;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\User;
use App\Services\PerformanceManagementV2\CoreFunctionGeneratorV2;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreFunctionGeneratorV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_generates_one_core_row_per_distinct_subject(): void
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'is_current' => true]);
        $teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create(['user_id' => $teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id]);
        $subject = Subject::create(['school_year_id' => $sy->id, 'code' => 'MATH1-'.uniqid(), 'name' => 'Math 1', 'grade_level' => 7]);

        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 3,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 3,
        ]);

        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026]);
        $ipcr = EmployeeIpcrV2::create([
            'user_id' => $teacher->id, 'rating_period_id' => $period->id, 'title' => 'Test', 'status' => 'New Target',
        ]);

        $created = app(CoreFunctionGeneratorV2::class)->generate($ipcr);

        $this->assertEquals(1, $created);
        $this->assertCount(1, $ipcr->rows()->where('function_type', 'core')->get());
    }
}
