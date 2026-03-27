<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\OverloadComputation;
use App\Models\FacultyLoading\SalarySchedule;
use App\Models\FacultyLoading\SstPosition;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 — Overload Pay Module tests.
 *
 * Covers:
 *   - SalarySchedule::lookupAnnualRate()   — current-schedule rate lookup
 *   - SalarySchedule::ratesForGrade()      — full step table for a grade
 *   - SalarySchedule::activateSchedule()   — switching the current SSL
 *   - User → sstPosition relationship      — FK resolution
 *   - OverloadComputationController@store  — salary-schedule-backed computation
 *   - OverloadComputationController@bulkCompute — batch generation
 *   - PHTR formula verification            — (AR / 1600) × 1.25
 */
class OverloadPayTest extends TestCase
{
    use RefreshDatabase;

    private LoadComputationService $svc;
    private User         $faculty;
    private SstPosition  $position;
    private AcademicTerm $term;
    private FacultyLoad  $load;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = new LoadComputationService();

        $schoolYear = \App\Models\FacultyLoading\SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01',
            'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active',
        ]);

        $this->term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id, 'name' => '1st Semester',
            'term_type' => '1st_semester', 'start_date' => '2025-08-01',
            'end_date' => '2025-12-31', 'is_current' => true,
        ]);

        $this->position = SstPosition::create([
            'code'                => 'SST-III',
            'name'                => 'Science and Technology Teacher III',
            'salary_grade'        => 14,
            'full_load_threshold' => 18.00,
            'min_teaching_units'  => 6.00,
            'max_admin_units'     => 15.00,
            'max_research_units'  => 5.00,
            'overload_threshold'  => 18.00,
            'max_overload_units'  => 6.00,
            'is_active'           => true,
        ]);

        $this->faculty = User::factory()->create([
            'email_verified_at' => now(),
            'sst_position_id'   => $this->position->id,
        ]);

        $this->load = FacultyLoad::create([
            'user_id'             => $this->faculty->id,
            'school_year_id'      => $schoolYear->id,
            'academic_term_id'    => $this->term->id,
            'teaching_units'      => 12.00,
            'research_units'      => 2.00,
            'admin_units'         => 6.00,
            'cocurricular_units'  => 1.00,
            'committee_units'     => 1.00,
            'total_units'         => 22.00,
            'full_load_threshold' => 18.00,
            'load_status'         => 'overload',
            'overload_approved'   => false,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function directorUser(): User
    {
        $role = Role::create(['name' => 'TestDirector_' . uniqid()]);
        $perm = Permission::firstOrCreate(
            ['name' => 'faculty_loading.approve'],
            ['module' => 'FacultyLoading', 'description' => 'Approve overloads']
        );
        $role->permissions()->attach($perm->id);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function seedSchedule(int $sg = 14, float $monthly = 39_000, bool $isCurrent = true): SalarySchedule
    {
        return SalarySchedule::create([
            'salary_grade'   => $sg,
            'step'           => 1,
            'monthly_rate'   => $monthly,
            'annual_rate'    => $monthly * 12,
            'effective_date' => '2023-01-01',
            'is_current'     => $isCurrent,
            'schedule_name'  => 'SSL V 2023',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SalarySchedule — rate lookup
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_lookup_returns_annual_rate_for_current_schedule(): void
    {
        $this->seedSchedule(14, 39_000);

        $rate = SalarySchedule::lookupAnnualRate(14, 1);

        $this->assertEqualsWithDelta(39_000 * 12, $rate, 0.01);
    }

    public function test_lookup_returns_null_when_no_current_entry(): void
    {
        $this->assertNull(SalarySchedule::lookupAnnualRate(14, 1));
    }

    public function test_lookup_ignores_non_current_schedule(): void
    {
        $this->seedSchedule(14, 39_000, isCurrent: false);

        $this->assertNull(SalarySchedule::lookupAnnualRate(14, 1));
    }

    public function test_rates_for_grade_returns_all_steps(): void
    {
        foreach ([1, 2, 3] as $step) {
            SalarySchedule::create([
                'salary_grade' => 14, 'step' => $step,
                'monthly_rate' => 39_000 + ($step - 1) * 800,
                'annual_rate'  => (39_000 + ($step - 1) * 800) * 12,
                'effective_date' => '2023-01-01', 'is_current' => true,
                'schedule_name' => 'SSL V 2023',
            ]);
        }

        $rates = SalarySchedule::ratesForGrade(14);

        $this->assertCount(3, $rates);
        $this->assertArrayHasKey(1, $rates);
        $this->assertArrayHasKey(2, $rates);
        $this->assertArrayHasKey(3, $rates);
        $this->assertEqualsWithDelta(39_000, $rates[1]['monthly_rate'], 0.01);
    }

    public function test_activate_schedule_flips_is_current(): void
    {
        // Seed two schedules
        SalarySchedule::create([
            'salary_grade' => 14, 'step' => 1,
            'monthly_rate' => 35_000, 'annual_rate' => 420_000,
            'effective_date' => '2021-01-01', 'is_current' => true,
            'schedule_name' => 'SSL IV 2021',
        ]);
        SalarySchedule::create([
            'salary_grade' => 14, 'step' => 1,
            'monthly_rate' => 39_000, 'annual_rate' => 468_000,
            'effective_date' => '2023-01-01', 'is_current' => false,
            'schedule_name' => 'SSL V 2023',
        ]);

        SalarySchedule::activateSchedule('SSL V 2023');

        $this->assertFalse(SalarySchedule::where('schedule_name', 'SSL IV 2021')->first()->is_current);
        $this->assertTrue(SalarySchedule::where('schedule_name', 'SSL V 2023')->first()->is_current);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // User → sstPosition relationship
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_user_has_sst_position_relationship(): void
    {
        $this->faculty->load('sstPosition');

        $this->assertNotNull($this->faculty->sstPosition);
        $this->assertSame('SST-III', $this->faculty->sstPosition->code);
        $this->assertSame(14, $this->faculty->sstPosition->salary_grade);
    }

    public function test_user_without_position_returns_null_sst_position(): void
    {
        $noPosition = User::factory()->create(['email_verified_at' => now(), 'sst_position_id' => null]);
        $this->assertNull($noPosition->sstPosition);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PHTR formula verification
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_phtr_formula_is_annual_rate_divided_by_1600_times_1_25(): void
    {
        $annualRate = 468_000.00; // SG 14 Step 1 approximate
        $phtr       = $this->svc->computePHTR($annualRate);

        $expected = ($annualRate / 1600) * 1.25;

        $this->assertEqualsWithDelta($expected, $phtr, 0.0001);
    }

    public function test_overload_pay_is_phtr_times_hours_times_weeks(): void
    {
        $phtr  = 365.625; // example PHTR
        $hours = 3.0;
        $weeks = 18;

        $pay = $this->svc->computeOverloadPay($phtr, $hours, $weeks);

        $this->assertEqualsWithDelta($phtr * $hours * $weeks, $pay, 0.01);
    }

    public function test_compute_overload_breakdown_uses_salary_schedule_rate(): void
    {
        $this->seedSchedule(14, 39_000);

        $rate      = SalarySchedule::lookupAnnualRate(14, 1);
        $breakdown = $this->svc->computeOverloadBreakdown($rate, 4.0, 3.0, 18);

        $expectedPhtr = ($rate / 1600) * 1.25;
        $expectedPay  = $expectedPhtr * 3.0 * 18;

        $this->assertEqualsWithDelta($expectedPhtr, $breakdown['phtr'], 0.0001);
        $this->assertEqualsWithDelta($expectedPay,  $breakdown['total_overload_pay'], 0.01);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Bulk computation
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_bulk_compute_creates_computation_for_overloaded_faculty(): void
    {
        $this->seedSchedule(14, 39_000);

        $this->actingAs($this->directorUser());

        $response = $this->post(route('faculty-loading.overload-computations.bulk-compute'), [
            'academic_term_id' => $this->term->id,
            'overload_hours'   => 3.0,
            'term_weeks'       => 18,
            'step'             => 1,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('overload_computations', [
            'user_id'         => $this->faculty->id,
            'academic_term_id'=> $this->term->id,
            'status'          => 'for_approval',
        ]);
    }

    public function test_bulk_compute_skips_faculty_without_salary_schedule(): void
    {
        // No salary schedule seeded — should skip

        $this->actingAs($this->directorUser());

        $this->post(route('faculty-loading.overload-computations.bulk-compute'), [
            'academic_term_id' => $this->term->id,
            'overload_hours'   => 3.0,
        ]);

        $this->assertDatabaseMissing('overload_computations', [
            'user_id' => $this->faculty->id,
        ]);
    }

    public function test_bulk_compute_skips_faculty_without_sst_position(): void
    {
        $this->seedSchedule(14, 39_000);

        // Create overloaded load for a faculty with no SST position
        $noPos = User::factory()->create(['email_verified_at' => now(), 'sst_position_id' => null]);

        $schoolYear = \App\Models\FacultyLoading\SchoolYear::where('is_current', true)->first();
        FacultyLoad::create([
            'user_id'             => $noPos->id,
            'school_year_id'      => $schoolYear->id,
            'academic_term_id'    => $this->term->id,
            'teaching_units'      => 12.00,
            'research_units'      => 2.00,
            'admin_units'         => 8.00,
            'cocurricular_units'  => 1.00,
            'committee_units'     => 1.00,
            'total_units'         => 24.00,
            'full_load_threshold' => 18.00,
            'load_status'         => 'overload',
            'overload_approved'   => false,
        ]);

        $this->actingAs($this->directorUser());

        $this->post(route('faculty-loading.overload-computations.bulk-compute'), [
            'academic_term_id' => $this->term->id,
            'overload_hours'   => 3.0,
        ]);

        // Only the faculty WITH position should have a computation
        $this->assertDatabaseHas('overload_computations', ['user_id' => $this->faculty->id]);
        $this->assertDatabaseMissing('overload_computations', ['user_id' => $noPos->id]);
    }

    public function test_bulk_compute_does_not_duplicate_existing_computations(): void
    {
        $this->seedSchedule(14, 39_000);

        // Pre-create a computation
        OverloadComputation::create([
            'user_id'            => $this->faculty->id,
            'faculty_load_id'    => $this->load->id,
            'school_year_id'     => $this->load->school_year_id,
            'academic_term_id'   => $this->term->id,
            'annual_rate'        => 468_000,
            'overload_units'     => 4,
            'overload_hours'     => 3,
            'phtr'               => 365.625,
            'total_overload_pay' => 19_744.00,
            'term_weeks'         => 18,
            'status'             => 'for_approval',
            'requested_by'       => null,
        ]);

        $this->actingAs($this->directorUser());

        $this->post(route('faculty-loading.overload-computations.bulk-compute'), [
            'academic_term_id' => $this->term->id,
            'overload_hours'   => 3.0,
        ]);

        $this->assertSame(1, OverloadComputation::where('user_id', $this->faculty->id)->count());
    }
}
