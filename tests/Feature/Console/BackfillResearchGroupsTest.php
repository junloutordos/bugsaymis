<?php

namespace Tests\Feature\Console;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackfillResearchGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_group_id_and_dedupes_co_advisers(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $lead = User::factory()->create();
        $co   = User::factory()->create();

        $leadRow = ResearchAdvisory::create(['user_id' => $lead->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'Legacy Title', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active']);
        $coRow   = ResearchAdvisory::create(['user_id' => $co->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'legacy title', 'grade_level' => 10, 'advisory_role' => 'co_adviser', 'research_type' => 'thesis', 'load_units' => 0.5, 'status' => 'active']);

        Artisan::call('research-groups:backfill');

        $leadRow->refresh();
        $coRow->refresh();
        $this->assertNotNull($leadRow->research_group_id);
        $this->assertSame($leadRow->research_group_id, $coRow->research_group_id);
        $this->assertSame(1, \App\Models\FacultyLoading\ResearchGroup::count());
    }

    public function test_dry_run_makes_no_changes(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $lead = User::factory()->create();
        ResearchAdvisory::create(['user_id' => $lead->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active']);

        Artisan::call('research-groups:backfill', ['--dry-run' => true]);

        $this->assertSame(0, \App\Models\FacultyLoading\ResearchGroup::count());
        $this->assertNull(ResearchAdvisory::first()->research_group_id);
    }
}
