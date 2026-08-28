<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\AgencyOutcome;
use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\IPCRRatingPeriod;
use App\Models\PerformanceIndicator;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\PerformanceManagement\FacultyIPCRBaselineService;
use App\Services\PerformanceManagement\WorkDistributionPlanClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the LoadAssignment / FacultyCommitteeAssignment ↔ WorkDistributionPlan
 * pivots, the Core/Support auto-classification default, and the baseline
 * service's direct-link attachment onto a faculty member's IPCR.
 */
class LoadAssignmentWorkDistributionPlanTest extends TestCase
{
    use RefreshDatabase;

    private function makeTerm(): array
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);

        return compact('sy', 'term');
    }

    private function makeLoadAssignment(array $fx, User $faculty, float $units, string $type = 'teaching'): LoadAssignment
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $faculty->id, 'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'teaching_units' => $units, 'total_units' => $units, 'full_load_threshold' => 18, 'load_status' => 'underload',
        ]);

        $subjectId = null;
        if ($type === 'teaching') {
            $subject = Subject::create([
                'school_year_id' => $fx['sy']->id,
                'code' => 'MATH' . uniqid(), 'name' => 'Mathematics 1', 'credit_units' => 3, 'lecture_hours' => 3,
                'load_units' => $units, 'subject_type' => 'lecture', 'grade_level' => 9,
                'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
            ]);
            $subjectId = $subject->id;
        }

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'assignment_type' => $type, 'subject_id' => $subjectId, 'load_units' => $units,
        ]);
    }

    private function makePlan(): WorkDistributionPlan
    {
        $outcome   = AgencyOutcome::create(['outcome' => 'X. Explicit Outcome', 'function_type' => 'Strategic Functions']);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Explicit Indicator']);

        return WorkDistributionPlan::create(['performance_indicator_id' => $indicator->id, 'success_indicator' => 'Explicit Plan']);
    }

    // ── Pivot relations ────────────────────────────────────────────────────

    public function test_load_assignment_can_link_multiple_work_distribution_plans(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $la      = $this->makeLoadAssignment($fx, $faculty, 3);

        $planA = $this->makePlan();
        $planB = $this->makePlan();

        $la->workDistributionPlans()->sync([$planA->id, $planB->id]);

        $this->assertCount(2, $la->workDistributionPlans);
        $this->assertTrue($la->workDistributionPlans->pluck('id')->contains($planA->id));
        $this->assertTrue($la->workDistributionPlans->pluck('id')->contains($planB->id));

        // Inverse relation
        $this->assertTrue($planA->loadAssignments->pluck('id')->contains($la->id));
    }

    public function test_committee_assignment_can_link_multiple_work_distribution_plans(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $fca     = FacultyCommitteeAssignment::create([
            'user_id' => $faculty->id, 'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'committee_name' => 'Ad Hoc Committee', 'role' => 'member', 'load_units' => 0, 'status' => 'active',
        ]);

        $planA = $this->makePlan();
        $planB = $this->makePlan();
        $fca->workDistributionPlans()->sync([$planA->id, $planB->id]);

        $this->assertCount(2, $fca->workDistributionPlans);
        $this->assertTrue($planB->facultyCommitteeAssignments->pluck('id')->contains($fca->id));
    }

    // ── hasUnitLoad() ──────────────────────────────────────────────────────

    public function test_has_unit_load_reflects_load_units(): void
    {
        $fx        = $this->makeTerm();
        $facultyA  = User::factory()->create();
        $facultyB  = User::factory()->create();

        $withUnits    = $this->makeLoadAssignment($fx, $facultyA, 3, 'teaching');
        $withoutUnits = $this->makeLoadAssignment($fx, $facultyB, 0, 'admin');

        $this->assertTrue($withUnits->hasUnitLoad());
        $this->assertFalse($withoutUnits->hasUnitLoad());

        $fcaWithUnits = FacultyCommitteeAssignment::create([
            'user_id' => $facultyA->id, 'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'committee_name' => 'Chair Committee', 'role' => 'chairperson', 'load_units' => 1.5, 'status' => 'active',
        ]);
        $fcaWithoutUnits = FacultyCommitteeAssignment::create([
            'user_id' => $facultyB->id, 'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'committee_name' => 'No-Load Committee', 'role' => 'member', 'load_units' => 0, 'status' => 'active',
        ]);

        $this->assertTrue($fcaWithUnits->hasUnitLoad());
        $this->assertFalse($fcaWithoutUnits->hasUnitLoad());
    }

    // ── WorkDistributionPlanClassifier ─────────────────────────────────────

    public function test_classifier_resolves_core_and_support_function_types(): void
    {
        $classifier = new WorkDistributionPlanClassifier();

        $this->assertSame('Core Functions', $classifier->functionTypeFor(true));
        $this->assertSame('Support Functions', $classifier->functionTypeFor(false));
    }

    public function test_classifier_gives_each_load_assignment_its_own_plan_never_merging(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $math    = $this->makeLoadAssignment($fx, $faculty, 3, 'teaching');

        $facultyB = User::factory()->create();
        $science  = $this->makeLoadAssignment($fx, $facultyB, 3, 'teaching');

        $classifier = new WorkDistributionPlanClassifier();
        $mathPlan    = $classifier->defaultPlanForLoadAssignment($math, 2026);
        $sciencePlan = $classifier->defaultPlanForLoadAssignment($science, 2026);

        // Two different assignments — even same units, same function type —
        // never share a plan.
        $this->assertNotSame($mathPlan->id, $sciencePlan->id);

        // Calling it again for the SAME assignment reuses the same plan
        // (idempotent, doesn't duplicate on repeated IPCR generation).
        $mathPlanAgain = $classifier->defaultPlanForLoadAssignment($math, 2026);
        $this->assertSame($mathPlan->id, $mathPlanAgain->id);
    }

    public function test_classifier_never_mutates_an_existing_explicitly_tagged_outcome(): void
    {
        $explicitPlan = $this->makePlan(); // tagged "Strategic Functions"
        $explicitOutcomeId = $explicitPlan->performance_indicator->agency_outcome_id;

        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $la      = $this->makeLoadAssignment($fx, $faculty, 3, 'teaching');

        $classifier = new WorkDistributionPlanClassifier();
        $classifier->defaultPlanForLoadAssignment($la, null);

        // The pre-existing, explicitly-tagged outcome must be untouched
        $this->assertSame(
            'Strategic Functions',
            AgencyOutcome::find($explicitOutcomeId)->function_type
        );
    }

    // ── FacultyIPCRBaselineService direct-link attachment ─────────────────

    private function makeIpcrFor(User $faculty, ?IPCRRatingPeriod $period = null): EmployeeIPCR
    {
        $period ??= IPCRRatingPeriod::create(['label' => 'FY 2026', 'year' => 2026]);

        return EmployeeIPCR::create([
            'user_id' => $faculty->id,
            'rating_period_id' => $period->id,
            'rating_period' => $period->label,
            'title' => 'Test IPCR',
            'status' => 'New Target',
        ]);
    }

    public function test_baseline_service_attaches_explicitly_linked_plan_for_a_load_assignment(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $la      = $this->makeLoadAssignment($fx, $faculty, 3, 'teaching');
        $plan    = $this->makePlan();
        $la->workDistributionPlans()->sync([$plan->id]);

        $ipcr = $this->makeIpcrFor($faculty);

        $summary = app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $this->assertGreaterThan(0, $summary['attached']);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $plan->id)->exists());
    }

    public function test_baseline_service_auto_classifies_unlinked_load_assignment_as_core_when_it_has_units(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $this->makeLoadAssignment($fx, $faculty, 3, 'teaching'); // no explicit plan link

        $ipcr = $this->makeIpcrFor($faculty);
        app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $corePlan = WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->first();

        $this->assertNotNull($corePlan);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $corePlan->id)->exists());
    }

    public function test_baseline_service_gives_each_subject_taught_its_own_ipcr_row_never_merging(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();

        // Two separate teaching loads for the same faculty, same term —
        // e.g. Math 1 and Science 1 — neither explicitly linked to a WDP.
        $facultyLoad = FacultyLoad::create([
            'user_id' => $faculty->id, 'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'teaching_units' => 6, 'total_units' => 6, 'full_load_threshold' => 18, 'load_status' => 'underload',
        ]);
        $math = Subject::create([
            'school_year_id' => $fx['sy']->id, 'code' => 'MATH1', 'name' => 'Mathematics 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $science = Subject::create([
            'school_year_id' => $fx['sy']->id, 'code' => 'SCI1', 'name' => 'Science 1', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $mathLoad = LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'assignment_type' => 'teaching', 'subject_id' => $math->id, 'load_units' => 3,
        ]);
        $scienceLoad = LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'assignment_type' => 'teaching', 'subject_id' => $science->id, 'load_units' => 3,
        ]);

        $ipcr = $this->makeIpcrFor($faculty);
        app(FacultyIPCRBaselineService::class)->generate($ipcr);

        // Both subjects auto-classify as Core Functions, but as two SEPARATE plans
        $corePlans = WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->get();

        $this->assertCount(2, $corePlans, 'Each subject taught must get its own IPCR row, not a merged one.');

        $attachedPlanIds = $ipcr->plans()->pluck('work_distribution_plans.id');
        foreach ($corePlans as $plan) {
            $this->assertTrue($attachedPlanIds->contains($plan->id));
        }

        // Re-generating the baseline again must not duplicate — same two plans reused
        app(FacultyIPCRBaselineService::class)->generate($ipcr);
        $this->assertCount(2, WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->get());
    }

    public function test_baseline_service_auto_classifies_unlinked_zero_unit_assignment_as_support(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $this->makeLoadAssignment($fx, $faculty, 0, 'admin'); // no units, no explicit plan link

        $ipcr = $this->makeIpcrFor($faculty);
        app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $supportPlan = WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Support Functions')
        )->first();

        $this->assertNotNull($supportPlan);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $supportPlan->id)->exists());
    }

    public function test_baseline_service_auto_classifies_unlinked_committee_assignment_without_load_as_support(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        FacultyCommitteeAssignment::create([
            'user_id' => $faculty->id, 'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'committee_name' => 'No-Load Committee', 'role' => 'member', 'load_units' => 0, 'status' => 'active',
        ]);

        $ipcr = $this->makeIpcrFor($faculty);
        app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $supportPlan = WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Support Functions')
        )->first();

        $this->assertNotNull($supportPlan);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $supportPlan->id)->exists());
    }

    public function test_baseline_service_prefers_explicit_committee_assignment_plan_over_auto_default(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $fca     = FacultyCommitteeAssignment::create([
            'user_id' => $faculty->id, 'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'committee_name' => 'Chair Committee', 'role' => 'chairperson', 'load_units' => 1.5, 'status' => 'active',
        ]);
        $explicitPlan = $this->makePlan();
        $fca->workDistributionPlans()->sync([$explicitPlan->id]);

        $ipcr = $this->makeIpcrFor($faculty);
        app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $explicitPlan->id)->exists());

        // No auto-generated Core/Support placeholder should have been created for this assignment
        $corePlan = WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->first();
        $this->assertNull($corePlan);
    }
}
