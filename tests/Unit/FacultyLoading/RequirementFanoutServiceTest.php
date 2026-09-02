<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\FacultyLoading\RequirementFanoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementFanoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();
        $sy = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $this->term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
    }

    private function makeActiveGroup(int $gradeLevel, string $researchType, string $title): ResearchGroup
    {
        $group = ResearchGroup::create(['academic_term_id' => $this->term->id, 'grade_level' => $gradeLevel, 'title' => $title, 'research_type' => $researchType]);
        ResearchAdvisory::create([
            'user_id' => User::factory()->create()->id, 'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'research_title' => $title, 'grade_level' => $gradeLevel, 'advisory_role' => 'lead', 'research_type' => $researchType,
            'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id,
        ]);
        return $group;
    }

    public function test_fans_out_to_groups_matching_grade_and_type(): void
    {
        $g10Thesis = $this->makeActiveGroup(10, 'thesis', 'Group A');
        $g11Thesis = $this->makeActiveGroup(11, 'thesis', 'Group B');
        $g10Invest = $this->makeActiveGroup(10, 'investigatory', 'Group C');

        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => [10], 'research_type' => 'thesis', 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);

        $created = (new RequirementFanoutService())->fanOut($requirement);

        $this->assertCount(1, $created);
        $this->assertSame($g10Thesis->id, $created->first()->research_group_id);
        $this->assertSame(1, ResearchRequirementAssignment::where('research_requirement_id', $requirement->id)->count());
    }

    public function test_null_scope_matches_all_grades_and_types(): void
    {
        $this->makeActiveGroup(10, 'thesis', 'Group A');
        $this->makeActiveGroup(11, 'investigatory', 'Group B');

        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => null, 'research_type' => null, 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);

        $created = (new RequirementFanoutService())->fanOut($requirement);

        $this->assertCount(2, $created);
    }

    public function test_dropped_only_group_is_excluded(): void
    {
        $group = ResearchGroup::create(['academic_term_id' => $this->term->id, 'grade_level' => 10, 'title' => 'Dropped Group', 'research_type' => 'thesis']);
        ResearchAdvisory::create([
            'user_id' => User::factory()->create()->id, 'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'research_title' => 'Dropped Group', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis',
            'load_units' => 1.0, 'status' => 'dropped', 'research_group_id' => $group->id,
        ]);

        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => null, 'research_type' => null, 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);

        $created = (new RequirementFanoutService())->fanOut($requirement);

        $this->assertCount(0, $created);
    }

    public function test_running_fan_out_twice_is_idempotent(): void
    {
        $this->makeActiveGroup(10, 'thesis', 'Group A');
        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => null, 'research_type' => null, 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);

        $service = new RequirementFanoutService();
        $first  = $service->fanOut($requirement);
        $second = $service->fanOut($requirement);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second); // nothing new to create
        $this->assertSame(1, ResearchRequirementAssignment::count());
    }

    public function test_sync_picks_up_a_group_created_after_the_requirement(): void
    {
        $this->makeActiveGroup(10, 'thesis', 'Group A');
        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => null, 'research_type' => null, 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);
        $service = new RequirementFanoutService();
        $service->fanOut($requirement);

        $this->makeActiveGroup(10, 'thesis', 'Group B (new)');
        $second = $service->fanOut($requirement);

        $this->assertCount(1, $second);
        $this->assertSame(2, ResearchRequirementAssignment::count());
    }
}
