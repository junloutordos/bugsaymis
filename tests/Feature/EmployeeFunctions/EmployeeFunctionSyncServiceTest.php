<?php

namespace Tests\Feature\EmployeeFunctions;

use App\Models\AgencyOutcome;
use App\Models\Committee;
use App\Models\EmployeeFunction;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\PerformanceIndicator;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\EmployeeFunctionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeFunctionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $sy = SchoolYear::create([
            'name' => '2026-2027', 'is_current' => true,
            'start_date' => '2026-06-01', 'end_date' => '2027-03-31',
        ]);

        return AcademicTerm::create([
            'school_year_id' => $sy->id,
            'name' => 'Full Term',
            'term_type' => 'full_term',
            'is_current' => true,
        ]);
    }

    private function subject(AcademicTerm $term, string $code, string $name, float $units): Subject
    {
        return Subject::create([
            'code' => $code, 'name' => $name, 'load_units' => $units,
            'grade_level' => 7, 'school_year_id' => $term->school_year_id, 'is_active' => true,
        ]);
    }

    private function facultyLoad(User $user, AcademicTerm $term): FacultyLoad
    {
        return FacultyLoad::create([
            'user_id' => $user->id,
            'school_year_id' => $term->school_year_id,
            'academic_term_id' => $term->id,
        ]);
    }

    private function plan(string $successIndicator, ?string $loadSource = null): WorkDistributionPlan
    {
        $outcome = AgencyOutcome::create(['outcome' => 'x ' . $successIndicator]);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'x']);

        return WorkDistributionPlan::create([
            'performance_indicator_id' => $indicator->id,
            'success_indicator' => $successIndicator,
            'load_source' => $loadSource,
        ]);
    }

    public function test_syncs_one_core_function_row_per_distinct_subject_with_weight_by_units(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subjectA = $this->subject($term, 'CHEM1', 'Chemistry 1', 4);
        $subjectB = $this->subject($term, 'CHEM2', 'Chemistry 2', 2);

        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subjectA->id, 'load_units' => 4,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subjectB->id, 'load_units' => 2,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $rows = EmployeeFunction::where('user_id', $teacher->id)->core()->autoSynced()->get();
        $this->assertCount(2, $rows);

        $chemA = $rows->firstWhere('label', 'Chemistry 1');
        $this->assertNotNull($chemA);
        $this->assertEqualsWithDelta(66.67, (float) $chemA->weight_percent, 0.01); // 4 / 6 * 100
    }

    public function test_re_sync_detaches_a_row_no_longer_backed_by_a_current_load_assignment(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subject = $this->subject($term, 'PHYS1', 'Physics 1', 4);

        $assignment = LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 4,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);
        $this->assertCount(1, EmployeeFunction::where('user_id', $teacher->id)->get());

        $assignment->delete();
        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $this->assertCount(0, EmployeeFunction::where('user_id', $teacher->id)->get());
    }

    public function test_re_sync_never_deletes_a_row_with_real_accomplishment_data(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subject = $this->subject($term, 'BIO1', 'Biology 1', 4);

        $assignment = LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 4,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);
        $function = EmployeeFunction::where('user_id', $teacher->id)->first();

        $period = \App\Models\IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $teacher->id, 'rating_period_id' => $period->id]);
        $record->coreItems()->create([
            'employee_function_id' => $function->id, 'label' => 'Biology 1',
            'actual_accomplishment' => 'Taught 30 students, submitted all grades on time.',
        ]);

        $assignment->delete();
        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $this->assertDatabaseHas('employee_functions', ['id' => $function->id]);
    }

    public function test_teaching_load_attaches_load_source_tagged_framework_plans(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subject = $this->subject($term, 'MATH1', 'Math 1', 4);
        $plan = $this->plan('Teaching Load framework indicator', 'teaching');

        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 4,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $row = EmployeeFunction::where('user_id', $teacher->id)->core()->firstOrFail();
        $this->assertSame([$plan->id], $row->workDistributionPlans()->pluck('work_distribution_plans.id')->all());
    }

    public function test_designation_backed_load_attaches_union_of_category_and_designation_tags(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $categoryPlan = $this->plan('Category-wide indicator');
        $designationPlan = $this->plan('Designation-specific indicator');
        $category = DesignationCategory::create(['code' => 'ICT', 'name' => 'ICT Coordinatorship']);
        $category->workDistributionPlans()->attach($categoryPlan->id);
        $designation = Designation::create(['designation_category_id' => $category->id, 'code' => 'ITMGR', 'name' => 'IT Manager', 'assignment_type' => 'admin']);
        $designation->workDistributionPlans()->attach($designationPlan->id);

        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'admin', 'designation_id' => $designation->id, 'load_units' => 3,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $row = EmployeeFunction::where('user_id', $teacher->id)->core()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$categoryPlan->id, $designationPlan->id],
            $row->workDistributionPlans()->pluck('work_distribution_plans.id')->all()
        );
    }

    public function test_zero_load_designation_creates_a_support_row_instead_of_being_dropped(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $category = DesignationCategory::create(['code' => 'DISC', 'name' => 'Discipline']);
        $designation = Designation::create(['designation_category_id' => $category->id, 'code' => 'POD', 'name' => 'Prefect of Discipline', 'assignment_type' => 'admin']);

        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'admin', 'designation_id' => $designation->id, 'load_units' => 0,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $row = EmployeeFunction::where('user_id', $teacher->id)->autoSynced()->firstOrFail();
        $this->assertSame('support', $row->function_type);
    }

    public function test_untagged_designation_load_gets_an_auto_classified_fallback_plan(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $category = DesignationCategory::create(['code' => 'ATH', 'name' => 'Athletics']);
        $designation = Designation::create(['designation_category_id' => $category->id, 'code' => 'COACH', 'name' => 'Coach', 'assignment_type' => 'admin']);

        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'admin', 'designation_id' => $designation->id, 'load_units' => 2,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $row = EmployeeFunction::where('user_id', $teacher->id)->autoSynced()->firstOrFail();
        $this->assertCount(1, $row->workDistributionPlans);
        $this->assertDatabaseHas('agency_org_outcomes', ['sub_outcome' => 'App\\Models\\FacultyLoading\\LoadAssignment#' . LoadAssignment::first()->id]);
    }

    public function test_other_typed_raw_load_attaches_load_source_matching_framework_plan(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $plan = $this->plan('Research framework indicator', 'research');

        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'research', 'load_units' => 3,
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $row = EmployeeFunction::where('user_id', $teacher->id)->core()->firstOrFail();
        $this->assertSame([$plan->id], $row->workDistributionPlans()->pluck('work_distribution_plans.id')->all());
    }

    public function test_committee_assignment_with_explicit_tag_attaches_it(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $committee = Committee::create(['name' => 'Discipline Committee']);
        $plan = $this->plan('Committee explicit indicator');
        $ca = FacultyCommitteeAssignment::create([
            'user_id' => $teacher->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $ca->workDistributionPlans()->attach($plan->id);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $row = EmployeeFunction::where('user_id', $teacher->id)->support()->firstOrFail();
        $this->assertSame('Discipline Committee', $row->label);
        $this->assertSame([$plan->id], $row->workDistributionPlans()->pluck('work_distribution_plans.id')->all());
    }

    public function test_committee_assignment_without_a_tag_gets_an_auto_classified_fallback_plan(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $teacher->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $row = EmployeeFunction::where('user_id', $teacher->id)->support()->firstOrFail();
        $this->assertCount(1, $row->workDistributionPlans);
    }

    public function test_personnel_assigned_plan_creates_a_row_classified_by_the_plans_own_function_type(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $outcome = AgencyOutcome::create(['outcome' => 'x', 'function_type' => 'Core Functions']);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'x']);
        $plan = WorkDistributionPlan::create(['performance_indicator_id' => $indicator->id, 'success_indicator' => 'Personnel plan indicator']);
        DB::table('plan_user')->insert(['work_distribution_plan_id' => $plan->id, 'user_id' => $teacher->id, 'created_at' => now(), 'updated_at' => now()]);

        (new EmployeeFunctionSyncService())->syncFromFacultyLoading($teacher);

        $row = EmployeeFunction::where('user_id', $teacher->id)->core()->firstOrFail();
        $this->assertSame([$plan->id], $row->workDistributionPlans()->pluck('work_distribution_plans.id')->all());
    }

    public function test_re_sync_is_idempotent_across_all_group_types(): void
    {
        $term = $this->currentTerm();
        $teacher = User::factory()->create();
        $facultyLoad = $this->facultyLoad($teacher, $term);
        $subject = $this->subject($term, 'ENG1', 'English 1', 4);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 4,
        ]);
        $committee = Committee::create(['name' => 'Some Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $teacher->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);

        $service = new EmployeeFunctionSyncService();
        $service->syncFromFacultyLoading($teacher);
        $countAfterFirst = EmployeeFunction::where('user_id', $teacher->id)->count();
        $service->syncFromFacultyLoading($teacher);
        $countAfterSecond = EmployeeFunction::where('user_id', $teacher->id)->count();

        $this->assertSame(2, $countAfterFirst);
        $this->assertSame($countAfterFirst, $countAfterSecond);
    }
}
