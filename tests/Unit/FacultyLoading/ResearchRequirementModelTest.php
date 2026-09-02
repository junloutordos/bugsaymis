<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchRequirementModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_requirement_with_grade_levels_array(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $coordinator = User::factory()->create();

        $req = ResearchRequirement::create([
            'created_by'             => $coordinator->id,
            'academic_term_id'       => $term->id,
            'title'                  => 'Chapter 1 Draft',
            'description'            => 'Submit the Introduction chapter.',
            'research_type'          => null,
            'grade_levels'           => [10, 11],
            'accepted_file_types'    => 'pdf,docx',
            'max_files'              => 3,
            'due_at'                 => now()->addDays(14),
            'allow_late_submission'  => false,
            'status'                 => 'active',
        ]);

        $fresh = $req->fresh();
        $this->assertSame([10, 11], $fresh->grade_levels);
        $this->assertFalse($fresh->allow_late_submission);
        $this->assertTrue($fresh->createdBy->is($coordinator));
    }
}
