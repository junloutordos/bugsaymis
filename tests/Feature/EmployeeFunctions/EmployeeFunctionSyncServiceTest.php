<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\EmployeeFunctionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFunctionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create([
            'name' => '2026-2027', 'is_current' => true,
            'start_date' => '2026-06-01', 'end_date' => '2027-03-31',
        ]);

        return AcademicTerm::create([
            'school_year_id' => $sy->id,
            'name' => 'Full Term',
            'term_type' => 'full_term',
            'is_current' => true,
        ]);
    }

    private function subject(AcademicTerm $term, string $code, string $name, float $units): Subject
    {
        return Subject::create([
            'code' => $code, 'name' => $name, 'load_units' => $units,
            'grade_level' => 7, 'school_year_id' => $term->school_year_id, 'is_active' => true,
        ]);
    }

    private function facultyLoad(User $user, AcademicTerm $term): FacultyLoad
    {
        return FacultyLoad::create([
            'user_id' => $user->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
        ]);
    }

    public function test_syncs_one_core_function_row_per_distinct_subject_with_weight_by_units(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subjectA = $this->subject($term, 'CHEM1', 'Chemistry 1', 4);
        $subjectB = $this->subject($term, 'CHEM2', 'Chemistry 2', 2);

        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subjectA->id, 'load_units' => 4,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subjectB->id, 'load_units' => 2,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $rows = EmployeeFunction::where('user_id', $teacher->id)->core()->autoSynced()->get();
        $this->assertCount(2, $rows);

        $chemA = $rows->firstWhere('label', 'Chemistry 1');
        $this->assertNotNull($chemA);
        $this->assertEqualsWithDelta(66.67, (float) $chemA->weight_percent, 0.01); // 4 / 6 * 100
    }

    public function test_re_sync_detaches_a_row_no_longer_backed_by_a_current_load_assignment(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subject = $this->subject($term, 'PHYS1', 'Physics 1', 4);

        $assignment = LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 4,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);
        $this->assertCount(1, EmployeeFunction::where('user_id', $teacher->id)->get());

        $assignment->delete();
        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $this->assertCount(0, EmployeeFunction::where('user_id', $teacher->id)->get());
    }
}
