<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class ResearchRequirementAssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequirement(): array
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $coordinator = User::factory()->create();
        $req = ResearchRequirement::create([
            'created_by' => $coordinator->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1',
            'due_at' => now()->addDays(14), 'status' => 'active',
        ]);
        return [$req, $group];
    }

    public function test_can_create_assignment_with_default_pending_status(): void
    {
        [$req, $group] = $this->makeRequirement();

        $assignment = ResearchRequirementAssignment::create([
            'research_requirement_id' => $req->id,
            'research_group_id'       => $group->id,
        ]);

        $this->assertSame('pending', $assignment->fresh()->status);
        $this->assertFalse($assignment->fresh()->excluded);
        $this->assertTrue($assignment->researchGroup->is($group));
        $this->assertTrue($assignment->requirement->is($req));
    }

    public function test_unique_constraint_prevents_duplicate_assignment(): void
    {
        [$req, $group] = $this->makeRequirement();
        ResearchRequirementAssignment::create(['research_requirement_id' => $req->id, 'research_group_id' => $group->id]);

        $this->expectException(QueryException::class);
        ResearchRequirementAssignment::create(['research_requirement_id' => $req->id, 'research_group_id' => $group->id]);
    }
}
