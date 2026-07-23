<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\FacultyLoading\ResearchAdvisoryReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchAdvisoryReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_changes_without_writing(): void
    {
        [$term, $users, $target] = $this->seedScenario();
        $before = ResearchAdvisory::orderBy('id')->get()->toArray();

        $result = app(ResearchAdvisoryReconciliationService::class)
            ->reconcile($term, $target);

        $this->assertSame(['keep' => 1, 'update' => 2, 'create' => 0, 'drop' => 1], $result['counts']);
        $this->assertSame($before, ResearchAdvisory::orderBy('id')->get()->toArray());
        $this->assertDatabaseHas('research_advisories', [
            'user_id' => $users['bayron']->id,
            'research_title' => 'Grade 11 GROUP 11-12',
            'status' => 'active',
        ]);
    }

    public function test_execute_reconciles_only_research_and_repairs_derived_loads(): void
    {
        [$term, $users, $target] = $this->seedScenario();
        $targetLoad = FacultyLoad::create([
            'user_id' => $users['target']->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'teaching_units' => 7,
            'total_units' => 7,
        ]);
        $teaching = LoadAssignment::create([
            'faculty_load_id' => $targetLoad->id,
            'user_id' => $users['target']->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'assignment_type' => 'teaching',
            'load_units' => 7,
            'description' => 'Existing teaching assignment',
        ]);

        $result = app(ResearchAdvisoryReconciliationService::class)
            ->reconcile($term, $target, true);

        $this->assertSame(['keep' => 1, 'update' => 2, 'create' => 0, 'drop' => 1], $result['counts']);
        $this->assertDatabaseHas('research_advisories', [
            'user_id' => $users['bayron']->id,
            'research_title' => 'Grade 11 GROUP 11-12',
            'load_units' => 2,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('research_advisories', [
            'user_id' => $users['target']->id,
            'research_title' => 'GRADE 10 GROUP 3-4',
            'load_units' => 1,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('research_advisories', [
            'user_id' => $users['target']->id,
            'research_title' => 'Grade 11 Group 13-14,20',
            'load_units' => 3,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('research_advisories', [
            'user_id' => $users['stale']->id,
            'status' => 'dropped',
            'load_assignment_id' => null,
        ]);
        $this->assertSame(0, ResearchAdvisory::active()->whereNull('load_assignment_id')->count());

        $this->assertDatabaseHas('load_assignments', [
            'id' => $teaching->id,
            'assignment_type' => 'teaching',
            'load_units' => 7,
        ]);
        $this->assertSame(2.0, $this->researchUnits($term, $users['bayron']));
        $this->assertSame(4.0, $this->researchUnits($term, $users['target']));
        $this->assertSame(0.0, $this->researchUnits($term, $users['wrong']));
        $this->assertSame(0.0, $this->researchUnits($term, $users['stale']));

        $targetLoad->refresh();
        $this->assertSame('7.00', $targetLoad->teaching_units);
        $this->assertSame('4.00', $targetLoad->research_units);
        $this->assertSame('11.00', $targetLoad->total_units);
    }

    /** @return array{AcademicTerm,array<string,User>,array<int,array<string,mixed>>} */
    private function seedScenario(): array
    {
        $schoolYear = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'is_current' => true,
            'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Full Term',
            'term_type' => '1st_semester',
            'is_current' => true,
        ]);
        $users = [
            'bayron' => User::factory()->create(['name' => 'Bayron, Janina Erika S.']),
            'target' => User::factory()->create(['name' => 'New Chemistry 2']),
            'wrong' => User::factory()->create(['name' => 'New Chemistry 1']),
            'stale' => User::factory()->create(['name' => 'Stale, Faculty']),
        ];

        $this->advisory($term, $users['bayron'], 'Grade 11 GROUP 11-12', 11, 2);
        $this->advisory($term, $users['wrong'], 'GRADE 10 GROUP 3-4', 10, 1);
        $this->advisory($term, $users['target'], 'Grade 11 GROUP 13-14', 11, 2);
        $this->advisory($term, $users['stale'], 'Grade 12 GROUP 99', 12, 1);

        $target = [
            ['title' => 'Grade 11 GROUP 11-12', 'grade' => 11, 'units' => 2, 'faculty' => 'Bayron, Janina Erika S.'],
            ['title' => 'GRADE 10 GROUP 3-4', 'grade' => 10, 'units' => 1, 'faculty' => 'New Chemistry 2'],
            ['title' => 'Grade 11 Group 13-14,20', 'grade' => 11, 'units' => 3, 'faculty' => 'New Chemistry 2'],
        ];

        return [$term, $users, $target];
    }

    private function advisory(AcademicTerm $term, User $user, string $title, int $grade, float $units): ResearchAdvisory
    {
        return ResearchAdvisory::create([
            'user_id' => $user->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
            'research_title' => $title,
            'grade_level' => $grade,
            'advisory_role' => 'lead',
            'research_type' => 'science_research',
            'load_units' => $units,
            'status' => 'active',
        ]);
    }

    private function researchUnits(AcademicTerm $term, User $user): float
    {
        return (float) LoadAssignment::where('academic_term_id', $term->id)
            ->where('user_id', $user->id)
            ->where('assignment_type', 'research')
            ->sum('load_units');
    }
}
