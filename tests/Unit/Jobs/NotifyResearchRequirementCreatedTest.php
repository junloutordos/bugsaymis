<?php

namespace Tests\Unit\Jobs;

use App\Jobs\NotifyResearchRequirementCreated;
use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyResearchRequirementCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_every_adviser_on_each_assigned_group(): void
    {
        Mail::fake();
        Notification::fake();

        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $lead = User::factory()->create();
        $co   = User::factory()->create();
        ResearchAdvisory::create(['user_id' => $lead->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        ResearchAdvisory::create(['user_id' => $co->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'co_adviser', 'research_type' => 'thesis', 'load_units' => 0.5, 'status' => 'active', 'research_group_id' => $group->id]);

        $requirement = ResearchRequirement::create(['created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        $assignment  = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);

        (new NotifyResearchRequirementCreated($requirement->id, [$assignment->id]))->handle();

        Mail::assertSent(ResearchRequirementMail::class, 2);
        Notification::assertSentTo([$lead, $co], \App\Notifications\RequestStatusNotification::class);
    }
}
