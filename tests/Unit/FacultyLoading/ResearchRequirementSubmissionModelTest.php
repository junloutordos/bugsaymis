<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchRequirementSubmissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_submission_with_default_pending_review(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $coordinator = User::factory()->create();
        $adviser     = User::factory()->create();
        $requirement = ResearchRequirement::create(['created_by' => $coordinator->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        $assignment  = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);

        $submission = ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id,
            'submitted_by' => $adviser->id,
            'notes'        => 'Attached draft.',
            'submitted_at' => now(),
            'is_late'      => false,
        ]);

        $fresh = $submission->fresh();
        $this->assertSame('pending', $fresh->review_status);
        $this->assertTrue($fresh->submittedBy->is($adviser));
        $this->assertTrue($fresh->assignment->is($assignment));
    }

    public function test_submission_has_many_files(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $requirement = ResearchRequirement::create(['created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        $assignment  = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
        $submission  = ResearchRequirementSubmission::create(['research_requirement_assignment_id' => $assignment->id, 'submitted_by' => User::factory()->create()->id, 'submitted_at' => now(), 'is_late' => false]);

        $file = \App\Models\FacultyLoading\ResearchRequirementSubmissionFile::create([
            'research_requirement_submission_id' => $submission->id,
            'original_filename' => 'chapter1.pdf',
            's3_key'             => 'research-requirements/1/chapter1.pdf',
            'mime_type'          => 'application/pdf',
            'size_bytes'         => 12345,
        ]);

        $this->assertCount(1, $submission->fresh()->files);
        $this->assertTrue($file->submission->is($submission));
    }
}
