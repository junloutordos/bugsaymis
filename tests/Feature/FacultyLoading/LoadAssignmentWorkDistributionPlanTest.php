<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\AgencyOutcome;
use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
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
 * Covers the DesignationCategory ↔ WorkDistributionPlan pivot (the "mother
 * record" pattern — one tag on a Category applies to every current and
 * future holder of ANY designation under that category), the Core/Support
 * auto-classification default for non-designation teaching loads, and the
 * baseline service's attachment onto a faculty member's IPCR.
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

    private function makeCategory(array $overrides = []): DesignationCategory
    {
        return DesignationCategory::create(array_merge([
            'code' => 'ADMIN-' . uniqid(), 'name' => 'Admin',
        ], $overrides));
    }

    private function makeTeachingAssignment(array $fx, User $faculty, float $units): LoadAssignment
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $faculty->id, 'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'teaching_units' => $units, 'total_units' => $units, 'full_load_threshold' => 18, 'load_status' => 'underload',
        ]);
        $subject = Subject::create([
            'school_year_id' => $fx['sy']->id,
            'code' => 'SUBJ' . uniqid(), 'name' => 'Mathematics 1', 'credit_units' => 3, 'lecture_hours' => 3,
            'load_units' => $units, 'subject_type' => 'lecture', 'grade_level' => 9,
            'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => $units,
        ]);
    }

    private function makeDesignation(array $overrides = [], ?DesignationCategory $category = null): Designation
    {
        $category ??= $this->makeCategory();

        return Designation::create(array_merge([
            'designation_category_id' => $category->id,
            'code'                    => 'DESIG-' . uniqid(),
            'name'                    => 'Prefect of Discipline',
            'load_units'              => 3,
            'assignment_type'         => 'admin',
            'is_active'               => true,
        ], $overrides));
    }

    private function makeDesignationAssignment(array $fx, User $faculty, Designation $designation, float $units): LoadAssignment
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $faculty->id, 'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'teaching_units' => 0, 'total_units' => $units, 'full_load_threshold' => 18, 'load_status' => 'underload',
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'assignment_type' => $designation->assignment_type, 'load_units' => $units,
            'designation_id' => $designation->id, 'description' => $designation->name,
        ]);
    }

    private function makePlan(): WorkDistributionPlan
    {
        $outcome   = AgencyOutcome::create(['outcome' => 'X. Explicit Outcome', 'function_type' => 'Strategic Functions']);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Explicit Indicator']);

        return WorkDistributionPlan::create(['performance_indicator_id' => $indicator->id, 'success_indicator' => 'Explicit Plan']);
    }

    // ── DesignationCategory ↔ WorkDistributionPlan pivot ────────────────────

    public function test_designation_category_can_link_multiple_work_distribution_plans(): void
    {
        $category = $this->makeCategory();
        $planA = $this->makePlan();
        $planB = $this->makePlan();

        $category->workDistributionPlans()->sync([$planA->id, $planB->id]);

        $this->assertCount(2, $category->workDistributionPlans);
        $this->assertTrue($category->workDistributionPlans->pluck('id')->contains($planA->id));
        $this->assertTrue($category->workDistributionPlans->pluck('id')->contains($planB->id));

        // Inverse relation
        $this->assertTrue($planA->designationCategories->pluck('id')->contains($category->id));
    }

    public function test_committee_assignment_can_still_link_multiple_work_distribution_plans(): void
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

    // ── WorkDistributionPlanClassifier (still used for non-designation loads) ──

    public function test_classifier_resolves_core_and_support_function_types(): void
    {
        $classifier = new WorkDistributionPlanClassifier();

        $this->assertSame('Core Functions', $classifier->functionTypeFor(true));
        $this->assertSame('Support Functions', $classifier->functionTypeFor(false));
    }

    public function test_classifier_gives_each_teaching_load_its_own_plan_never_merging(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $math    = $this->makeTeachingAssignment($fx, $faculty, 3);

        $facultyB = User::factory()->create();
        $science  = $this->makeTeachingAssignment($fx, $facultyB, 3);

        $classifier = new WorkDistributionPlanClassifier();
        $mathPlan    = $classifier->defaultPlanForLoadAssignment($math, 2026);
        $sciencePlan = $classifier->defaultPlanForLoadAssignment($science, 2026);

        $this->assertNotSame($mathPlan->id, $sciencePlan->id);

        $mathPlanAgain = $classifier->defaultPlanForLoadAssignment($math, 2026);
        $this->assertSame($mathPlan->id, $mathPlanAgain->id);
    }

    public function test_classifier_never_mutates_an_existing_explicitly_tagged_outcome(): void
    {
        $explicitPlan = $this->makePlan(); // tagged "Strategic Functions"
        $explicitOutcomeId = $explicitPlan->performance_indicator->agency_outcome_id;

        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();
        $la      = $this->makeTeachingAssignment($fx, $faculty, 3);

        $classifier = new WorkDistributionPlanClassifier();
        $classifier->defaultPlanForLoadAssignment($la, null);

        $this->assertSame(
            'Strategic Functions',
            AgencyOutcome::find($explicitOutcomeId)->function_type
        );
    }

    // ── FacultyIPCRBaselineService ─────────────────────────────────────────

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

    public function test_baseline_service_attaches_category_tagged_plans_to_a_holder(): void
    {
        $fx          = $this->makeTerm();
        $faculty     = User::factory()->create();
        $designation = $this->makeDesignation();
        $plan        = $this->makePlan();
        $designation->category->workDistributionPlans()->sync([$plan->id]);

        $this->makeDesignationAssignment($fx, $faculty, $designation, 3);

        $ipcr = $this->makeIpcrFor($faculty);
        $summary = app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $this->assertGreaterThan(0, $summary['attached']);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $plan->id)->exists());
    }

    public function test_baseline_service_inherits_the_same_plans_for_every_current_holder_of_a_designation(): void
    {
        $fx          = $this->makeTerm();
        $designation = $this->makeDesignation();
        $plan        = $this->makePlan();
        $designation->category->workDistributionPlans()->sync([$plan->id]);

        $facultyA = User::factory()->create();
        $facultyB = User::factory()->create();
        $this->makeDesignationAssignment($fx, $facultyA, $designation, 3);
        $this->makeDesignationAssignment($fx, $facultyB, $designation, 3);

        $ipcrA = $this->makeIpcrFor($facultyA);
        $ipcrB = $this->makeIpcrFor($facultyB);
        app(FacultyIPCRBaselineService::class)->generate($ipcrA);
        app(FacultyIPCRBaselineService::class)->generate($ipcrB);

        $this->assertTrue($ipcrA->plans()->where('work_distribution_plans.id', $plan->id)->exists());
        $this->assertTrue($ipcrB->plans()->where('work_distribution_plans.id', $plan->id)->exists());
    }

    public function test_baseline_service_cascades_category_tagged_plans_to_every_designation_in_that_category(): void
    {
        $fx       = $this->makeTerm();
        $category = $this->makeCategory(['name' => 'Coordinatorship']);
        $mathCoord = $this->makeDesignation(['name' => 'Math Coordinator', 'code' => 'MATH-COORD-' . uniqid()], $category);
        $sciCoord  = $this->makeDesignation(['name' => 'Science Coordinator', 'code' => 'SCI-COORD-' . uniqid()], $category);

        // Tagged ONCE on the category — not on either individual designation.
        $plan = $this->makePlan();
        $category->workDistributionPlans()->sync([$plan->id]);

        $facultyA = User::factory()->create();
        $facultyB = User::factory()->create();
        $this->makeDesignationAssignment($fx, $facultyA, $mathCoord, 3);
        $this->makeDesignationAssignment($fx, $facultyB, $sciCoord, 3);

        $ipcrA = $this->makeIpcrFor($facultyA);
        $ipcrB = $this->makeIpcrFor($facultyB);
        app(FacultyIPCRBaselineService::class)->generate($ipcrA);
        app(FacultyIPCRBaselineService::class)->generate($ipcrB);

        $this->assertTrue($ipcrA->plans()->where('work_distribution_plans.id', $plan->id)->exists());
        $this->assertTrue($ipcrB->plans()->where('work_distribution_plans.id', $plan->id)->exists());
    }

    public function test_baseline_service_inherits_plans_for_a_future_holder_tagged_after_the_fact(): void
    {
        $fx          = $this->makeTerm();
        $designation = $this->makeDesignation();

        // Category tagged with a plan AFTER a faculty is already assigned —
        // proves the "mother record" pattern: no per-assignment re-tagging needed.
        $facultyA = User::factory()->create();
        $this->makeDesignationAssignment($fx, $facultyA, $designation, 3);

        $plan = $this->makePlan();
        $designation->category->workDistributionPlans()->sync([$plan->id]);

        // A second faculty holds the SAME designation, assigned after the tag
        $facultyB = User::factory()->create();
        $this->makeDesignationAssignment($fx, $facultyB, $designation, 3);

        $ipcrA = $this->makeIpcrFor($facultyA);
        $ipcrB = $this->makeIpcrFor($facultyB);
        app(FacultyIPCRBaselineService::class)->generate($ipcrA);
        app(FacultyIPCRBaselineService::class)->generate($ipcrB);

        $this->assertTrue($ipcrA->plans()->where('work_distribution_plans.id', $plan->id)->exists());
        $this->assertTrue($ipcrB->plans()->where('work_distribution_plans.id', $plan->id)->exists());
    }

    public function test_baseline_service_falls_back_to_auto_classified_core_row_for_a_designation_whose_category_has_no_plans_tagged(): void
    {
        $fx          = $this->makeTerm();
        $faculty     = User::factory()->create();
        $designation = $this->makeDesignation(); // category has no WDPs tagged

        $this->makeDesignationAssignment($fx, $faculty, $designation, 3); // carries units

        $ipcr = $this->makeIpcrFor($faculty);
        $summary = app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $this->assertSame(1, $summary['attached']);
        $corePlan = WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->first();
        $this->assertNotNull($corePlan);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $corePlan->id)->exists());
    }

    public function test_baseline_service_falls_back_to_auto_classified_support_row_for_a_zero_unit_designation_with_no_category_tag(): void
    {
        $fx          = $this->makeTerm();
        $faculty     = User::factory()->create();
        $designation = $this->makeDesignation(); // category has no WDPs tagged

        $this->makeDesignationAssignment($fx, $faculty, $designation, 0); // no units

        $ipcr = $this->makeIpcrFor($faculty);
        $summary = app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $this->assertSame(1, $summary['attached']);
        $supportPlan = WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Support Functions')
        )->first();
        $this->assertNotNull($supportPlan);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $supportPlan->id)->exists());
    }

    public function test_baseline_service_does_not_also_auto_generate_a_fallback_row_when_the_category_is_already_tagged(): void
    {
        $fx          = $this->makeTerm();
        $faculty     = User::factory()->create();
        $designation = $this->makeDesignation();
        $plan        = $this->makePlan();
        $designation->category->workDistributionPlans()->sync([$plan->id]);

        $this->makeDesignationAssignment($fx, $faculty, $designation, 3); // carries units

        $ipcr = $this->makeIpcrFor($faculty);
        $summary = app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $this->assertSame(1, $summary['attached']);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $plan->id)->exists());
        $this->assertNull(WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->first());
    }

    // ── Designation-level tagging (union with category) ─────────────────────

    public function test_designation_own_plans_attach_in_union_with_its_category_plans(): void
    {
        $fx          = $this->makeTerm();
        $faculty     = User::factory()->create();
        $designation = $this->makeDesignation();
        $categoryPlan    = $this->makePlan();
        $designationPlan = $this->makePlan();
        $designation->category->workDistributionPlans()->sync([$categoryPlan->id]);
        $designation->workDistributionPlans()->sync([$designationPlan->id]);

        $this->makeDesignationAssignment($fx, $faculty, $designation, 3);

        $ipcr = $this->makeIpcrFor($faculty);
        $summary = app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $this->assertSame(2, $summary['attached']);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $categoryPlan->id)->exists());
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $designationPlan->id)->exists());
    }

    public function test_baseline_service_falls_back_only_when_both_category_and_designation_are_untagged(): void
    {
        $fx          = $this->makeTerm();
        $faculty     = User::factory()->create();
        $designation = $this->makeDesignation(); // category untagged
        $designationPlan = $this->makePlan();
        $designation->workDistributionPlans()->sync([$designationPlan->id]); // only the designation itself is tagged

        $this->makeDesignationAssignment($fx, $faculty, $designation, 3);

        $ipcr = $this->makeIpcrFor($faculty);
        $summary = app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $this->assertSame(1, $summary['attached']);
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $designationPlan->id)->exists());
        $this->assertNull(WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->first());
    }

    // ── Teaching Load framework-plan tagging (replaces per-subject fallback) ─

    public function test_baseline_service_uses_a_tagged_teaching_load_plan_instead_of_per_subject_fallback_rows(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();

        $outcome   = AgencyOutcome::create(['outcome' => 'Teaching Outcome', 'function_type' => 'Core Functions']);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Teaching Indicator']);
        $teachingPlan = WorkDistributionPlan::create([
            'performance_indicator_id' => $indicator->id,
            'success_indicator' => 'Delivers instruction per DepEd curriculum',
            'load_source' => 'teaching',
        ]);

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
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'assignment_type' => 'teaching', 'subject_id' => $math->id, 'load_units' => 3,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'assignment_type' => 'teaching', 'subject_id' => $science->id, 'load_units' => 3,
        ]);

        $ipcr = $this->makeIpcrFor($faculty);
        app(FacultyIPCRBaselineService::class)->generate($ipcr);

        // Only the tagged Teaching Load plan attaches — no auto-generated
        // per-subject fallback rows alongside it.
        $this->assertTrue($ipcr->plans()->where('work_distribution_plans.id', $teachingPlan->id)->exists());
        $this->assertNull(WorkDistributionPlan::where('id', '!=', $teachingPlan->id)->whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->first());

        $target = \Illuminate\Support\Facades\DB::table('employee_ipcrs_plan')
            ->where('ipcr_id', $ipcr->id)->where('plan_id', $teachingPlan->id)
            ->value('individual_target');
        $this->assertStringContainsString('Mathematics 1', $target);
        $this->assertStringContainsString('Science 1', $target);
    }

    public function test_baseline_service_gives_each_subject_taught_its_own_ipcr_row_never_merging(): void
    {
        $fx      = $this->makeTerm();
        $faculty = User::factory()->create();

        // Two separate raw teaching loads (no designation) for the same
        // faculty, same term — e.g. Math 1 and Science 1.
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
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'assignment_type' => 'teaching', 'subject_id' => $math->id, 'load_units' => 3,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $faculty->id,
            'school_year_id' => $fx['sy']->id, 'academic_term_id' => $fx['term']->id,
            'assignment_type' => 'teaching', 'subject_id' => $science->id, 'load_units' => 3,
        ]);

        $ipcr = $this->makeIpcrFor($faculty);
        app(FacultyIPCRBaselineService::class)->generate($ipcr);

        $corePlans = WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->get();

        $this->assertCount(2, $corePlans, 'Each subject taught must get its own IPCR row, not a merged one.');

        $attachedPlanIds = $ipcr->plans()->pluck('work_distribution_plans.id');
        foreach ($corePlans as $plan) {
            $this->assertTrue($attachedPlanIds->contains($plan->id));
        }

        // Re-generating must not duplicate — same two plans reused
        app(FacultyIPCRBaselineService::class)->generate($ipcr);
        $this->assertCount(2, WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->get());
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

        $corePlan = WorkDistributionPlan::whereHas(
            'performanceIndicator.agencyOutcome',
            fn ($q) => $q->where('function_type', 'Core Functions')
        )->first();
        $this->assertNull($corePlan);
    }
}
