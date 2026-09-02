<?php

namespace Tests\Unit\Jobs;

use App\Jobs\NotifyResearchSubmissionReceived;
use App\Jobs\NotifyResearchSubmissionReviewed;
use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyResearchSubmissionJobsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmission(): array
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $coordinator = User::factory()->create();
        $adviser     = User::factory()->create();
        $requirement = ResearchRequirement::create(['created_by' => $coordinator->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        $assignment  = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
        $submission  = ResearchRequirementSubmission::create(['research_requirement_assignment_id' => $assignment->id, 'submitted_by' => $adviser->id, 'submitted_at' => now(), 'is_late' => false]);
        return [$submission, $coordinator, $adviser];
    }

    public function test_received_job_notifies_the_coordinator(): void
    {
        Mail::fake();
        [$submission, $coordinator] = $this->makeSubmission();

        (new NotifyResearchSubmissionReceived($submission->id))->handle();

        Mail::assertSent(ResearchRequirementMail::class, fn ($mail) => $mail->hasTo($coordinator->email));
    }

    public function test_reviewed_job_notifies_the_submitter(): void
    {
        Mail::fake();
        [$submission, , $adviser] = $this->makeSubmission();
        $submission->update(['review_status' => 'accepted']);

        (new NotifyResearchSubmissionReviewed($submission->id))->handle();

        Mail::assertSent(ResearchRequirementMail::class, fn ($mail) => $mail->hasTo($adviser->email));
    }
}
