<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchGroupModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_read_research_group(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);

        $group = ResearchGroup::create([
            'academic_term_id' => $term->id,
            'grade_level'      => 10,
            'title'            => 'The Effects of X on Y',
            'research_type'    => 'investigatory',
        ]);

        $this->assertDatabaseHas('research_groups', [
            'id'    => $group->id,
            'title' => 'The Effects of X on Y',
        ]);
        $this->assertSame(10, $group->fresh()->grade_level);
    }

    public function test_research_advisory_belongs_to_a_research_group(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);

        $faculty = \App\Models\User::factory()->create();
        $advisory = \App\Models\FacultyLoading\ResearchAdvisory::create([
            'user_id' => $faculty->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'lead',
            'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active',
            'research_group_id' => $group->id,
        ]);

        $this->assertTrue($advisory->researchGroup->is($group));
        $this->assertCount(1, $group->fresh()->advisories);
    }
}
