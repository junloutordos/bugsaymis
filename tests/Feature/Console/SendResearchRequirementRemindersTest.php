<?php

namespace Tests\Feature\Console;

use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendResearchRequirementRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(array $requirementOverrides = []): array
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $adviser = User::factory()->create();
        ResearchAdvisory::create(['user_id' => $adviser->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        $coordinator = User::factory()->create();
        $requirement = ResearchRequirement::create(array_merge([
            'created_by' => $coordinator->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(2), 'status' => 'active',
        ], $requirementOverrides));
        $assignment = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
        return [$assignment, $adviser, $coordinator];
    }

    public function test_sends_reminder_when_due_within_3_days_and_stamps_guard(): void
    {
        Mail::fake();
        [$assignment] = $this->makeSetup(['due_at' => now()->addDays(2)]);

        Artisan::call('research:send-requirement-reminders');

        Mail::assertSent(ResearchRequirementMail::class);
        $this->assertNotNull($assignment->fresh()->reminder_sent_at);
    }

    public function test_does_not_send_reminder_twice(): void
    {
        Mail::fake();
        [$assignment] = $this->makeSetup(['due_at' => now()->addDays(2)]);

        Artisan::call('research:send-requirement-reminders');
        Mail::fake(); // reset the sent-mail counter for a clean second assertion
        Artisan::call('research:send-requirement-reminders');

        Mail::assertNotSent(ResearchRequirementMail::class);
    }

    public function test_sends_overdue_notice_to_adviser_and_coordinator(): void
    {
        Mail::fake();
        [$assignment, $adviser, $coordinator] = $this->makeSetup(['due_at' => now()->subDay()]);

        Artisan::call('research:send-requirement-reminders');

        Mail::assertSent(ResearchRequirementMail::class, fn ($mail) => $mail->hasTo($adviser->email));
        Mail::assertSent(ResearchRequirementMail::class, fn ($mail) => $mail->hasTo($coordinator->email));
        $this->assertNotNull($assignment->fresh()->overdue_notified_at);
    }

    public function test_accepted_assignment_is_never_reminded(): void
    {
        Mail::fake();
        [$assignment] = $this->makeSetup(['due_at' => now()->addDays(2)]);
        $assignment->update(['status' => 'accepted']);

        Artisan::call('research:send-requirement-reminders');

        Mail::assertNotSent(ResearchRequirementMail::class);
    }
}
