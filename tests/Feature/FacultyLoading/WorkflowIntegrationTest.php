<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\OverloadComputation;
use App\Models\FacultyLoading\SalarySchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\SstPosition;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8 — End-to-end workflow integration tests.
 *
 * Tests the complete faculty load lifecycle:
 *   1. CID creates a load assignment → FacultyLoad auto-created, syncLoad() runs
 *   2. Overload detected → load_status = 'overload'
 *   3. OCD bulk-computes → OverloadComputation created
 *   4. OCD approves computation → FacultyLoad.overload_approved = true
 *   5. OCD marks as paid → FacultyLoad.is_locked = true
 *   6. Lock enforced → CID cannot mutate locked load
 *   7. OCD manually unlocks → CID can mutate again
 *   8. Faculty views their load via My Load page
 */
class WorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User        $cid;
    private User        $ocd;
    private User        $faculty;
    private SchoolYear  $sy;
    private AcademicTerm $term;
    private SstPosition $position;
    private Subject     $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // CID user (manage)
        $cidRole = Role::create(['name' => 'CID_' . uniqid()]);
        foreach (['faculty_loading.view', 'faculty_loading.manage'] as $p) {
            $perm = Permission::firstOrCreate(['name' => $p], ['module' => 'FL', 'description' => $p]);
            $cidRole->permissions()->attach($perm->id);
        }
        $this->cid = User::factory()->create(['email_verified_at' => now()]);
        $this->cid->roles()->attach($cidRole->id);

        // OCD user (approve)
        $ocdRole = Role::create(['name' => 'OCD_' . uniqid()]);
        foreach (['faculty_loading.view', 'faculty_loading.approve'] as $p) {
            $perm = Permission::firstOrCreate(['name' => $p], ['module' => 'FL', 'description' => $p]);
            $ocdRole->permissions()->attach($perm->id);
        }
        $this->ocd = User::factory()->create(['email_verified_at' => now()]);
        $this->ocd->roles()->attach($ocdRole->id);

        // Faculty user (view_own)
        $facRole = Role::create(['name' => 'Faculty_' . uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.view_own'], ['module' => 'FL', 'description' => 'view_own']);
        $facRole->permissions()->attach($perm->id);

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
        $this->faculty->roles()->attach($facRole->id);

        $this->sy = SchoolYear::create([
            'name'       => '2025-2026', 'start_date' => '2025-08-01',
            'end_date'   => '2026-06-30', 'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id,
            'name'           => '1st Semester',
            'term_type'      => '1st_semester',
            'start_date'     => '2025-08-01',
            'end_date'       => '2025-12-31',
            'is_current'     => true,
        ]);
        $this->subject = Subject::create([
            'code'                => 'SCI-101',
            'name'                => 'General Science',
            'subject_type'        => 'lecture',
            'grade_level'         => 9,
            'credit_units'        => 6,
            'lecture_hours'       => 6,
            'load_units'          => 6.00,
            'sessions_per_week'   => 6,
            'minutes_per_session' => 60,
            'is_active'           => true,
        ]);

        SalarySchedule::create([
            'salary_grade'   => 14,
            'step'           => 1,
            'monthly_rate'   => 39_000,
            'annual_rate'    => 468_000,
            'effective_date' => '2023-01-01',
            'is_current'     => true,
            'schedule_name'  => 'SSL V 2023',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Step 1 — CID creates a load assignment → FacultyLoad auto-created
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_step1_cid_creates_assignment_and_faculty_load_is_auto_created(): void
    {
        $this->actingAs($this->cid)
            ->post(route('faculty-loading.assignments.store'), [
                'user_id'          => $this->faculty->id,
                'school_year_id'   => $this->sy->id,
                'academic_term_id' => $this->term->id,
                'assignment_type'  => 'teaching',
                'subject_id'       => $this->subject->id,
                'load_units'       => 6.0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('faculty_loads', [
            'user_id'          => $this->faculty->id,
            'academic_term_id' => $this->term->id,
            'teaching_units'   => 6.0,
            'load_status'      => 'underload',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Step 2 — Adding more assignments triggers overload detection
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_step2_additional_assignments_trigger_overload_status(): void
    {
        // Create enough assignments to exceed 18-unit threshold
        foreach ([6.0, 6.0, 6.0, 4.0] as $units) {
            $this->actingAs($this->cid)
                ->post(route('faculty-loading.assignments.store'), [
                    'user_id'          => $this->faculty->id,
                    'school_year_id'   => $this->sy->id,
                    'academic_term_id' => $this->term->id,
                    'assignment_type'  => 'teaching',
                    'subject_id'       => $this->subject->id,
                    'load_units'       => $units,
                ]);
        }

        $load = FacultyLoad::where('user_id', $this->faculty->id)
            ->where('academic_term_id', $this->term->id)
            ->first();

        $this->assertSame('overload', $load->load_status);
        $this->assertEqualsWithDelta(22.0, (float) $load->total_units, 0.01);
        $this->assertEqualsWithDelta(4.0, $load->overload_units, 0.01);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Step 3 — OCD bulk-computes → OverloadComputation created
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_step3_ocd_bulk_compute_creates_computation(): void
    {
        $this->buildOverloadLoad();

        $this->actingAs($this->ocd)
            ->post(route('faculty-loading.overload-computations.bulk-compute'), [
                'academic_term_id' => $this->term->id,
                'overload_hours'   => 3.0,
                'term_weeks'       => 18,
                'step'             => 1,
            ])
            ->assertRedirect();

        $computation = OverloadComputation::where('user_id', $this->faculty->id)->first();

        $this->assertNotNull($computation);
        $this->assertSame('for_approval', $computation->status);

        // Verify PHTR formula: (468000 / 1600) * 1.25
        $expectedPhtr = (468_000 / 1600) * 1.25;
        $this->assertEqualsWithDelta($expectedPhtr, (float) $computation->phtr, 0.01);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Step 4 — OCD approves → FacultyLoad.overload_approved = true
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_step4_ocd_approves_computation(): void
    {
        $load        = $this->buildOverloadLoad();
        $computation = $this->createComputation($load, 'for_approval');

        $this->actingAs($this->ocd)
            ->post(route('faculty-loading.overload-computations.approve', $computation->id), [
                'approved' => true,
                'remarks'  => 'Approved for exceptional service.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('overload_computations', [
            'id'     => $computation->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('faculty_loads', [
            'id'               => $load->id,
            'overload_approved'=> true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Step 5 — OCD marks paid → FacultyLoad.is_locked = true
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_step5_mark_paid_locks_the_faculty_load(): void
    {
        $load        = $this->buildOverloadLoad();
        $computation = $this->createComputation($load, 'approved');

        $this->actingAs($this->ocd)
            ->post(route('faculty-loading.overload-computations.mark-paid', $computation->id))
            ->assertRedirect();

        $this->assertDatabaseHas('overload_computations', [
            'id'     => $computation->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('faculty_loads', [
            'id'        => $load->id,
            'is_locked' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Step 6 — Lock enforced: CID cannot add assignments to locked load
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_step6_locked_load_rejects_new_assignment(): void
    {
        $load = $this->buildOverloadLoad();
        $load->update(['is_locked' => true]);

        $this->actingAs($this->cid)
            ->post(route('faculty-loading.assignments.store'), [
                'user_id'          => $this->faculty->id,
                'school_year_id'   => $this->sy->id,
                'academic_term_id' => $this->term->id,
                'assignment_type'  => 'teaching',
                'subject_id'       => $this->subject->id,
                'load_units'       => 3.0,
            ])
            ->assertSessionHasErrors('faculty_load_id');
    }

    public function test_step6_locked_load_rejects_update(): void
    {
        $load = $this->buildOverloadLoad();
        $assignment = $load->assignments()->first();
        $load->update(['is_locked' => true]);

        $this->actingAs($this->cid)
            ->put(route('faculty-loading.assignments.update', $assignment->id), [
                'assignment_type' => 'teaching',
                'subject_id'      => $this->subject->id,
                'load_units'      => 5.0,
            ])
            ->assertSessionHasErrors('faculty_load_id');
    }

    public function test_step6_locked_load_rejects_delete(): void
    {
        $load = $this->buildOverloadLoad();
        $assignment = $load->assignments()->first();
        $load->update(['is_locked' => true]);

        $this->actingAs($this->cid)
            ->delete(route('faculty-loading.assignments.destroy', $assignment->id))
            ->assertSessionHasErrors('faculty_load_id');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Step 7 — OCD manually unlocks → CID can mutate again
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_step7_unlock_allows_mutations_again(): void
    {
        $load = $this->buildOverloadLoad();
        $load->update(['is_locked' => true]);

        // OCD unlocks
        $this->actingAs($this->ocd)
            ->post(route('faculty-loading.unlock', $load->id))
            ->assertRedirect();

        $this->assertDatabaseHas('faculty_loads', ['id' => $load->id, 'is_locked' => false]);

        // CID can now add assignment
        $this->actingAs($this->cid)
            ->post(route('faculty-loading.assignments.store'), [
                'user_id'          => $this->faculty->id,
                'school_year_id'   => $this->sy->id,
                'academic_term_id' => $this->term->id,
                'assignment_type'  => 'admin',
                'load_units'       => 1.0,
                'description'      => 'Extra admin duty',
            ])
            ->assertSessionHasNoErrors();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Step 8 — Faculty views their My Load page (includes OC computation)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_step8_faculty_sees_my_load_with_overload_computation(): void
    {
        $load        = $this->buildOverloadLoad();
        $computation = $this->createComputation($load, 'approved');

        $this->actingAs($this->faculty)
            ->get(route('faculty-loading.my-load', ['term_id' => $this->term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/MyLoad')
                ->has('load')
                ->where('load.load_status', 'overload')
                ->where('load.overload_computation.status', 'approved')
                ->where('load.overload_computation.phtr', (float) $computation->phtr)
            );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════════

    /** Build an overloaded FacultyLoad with one assignment (22 units total). */
    private function buildOverloadLoad(): FacultyLoad
    {
        $load = FacultyLoad::create([
            'user_id'             => $this->faculty->id,
            'school_year_id'      => $this->sy->id,
            'academic_term_id'    => $this->term->id,
            'teaching_units'      => 22.0,
            'research_units'      => 0,
            'admin_units'         => 0,
            'cocurricular_units'  => 0,
            'committee_units'     => 0,
            'total_units'         => 22.0,
            'full_load_threshold' => 18.0,
            'load_status'         => 'overload',
            'overload_approved'   => false,
        ]);

        \App\Models\FacultyLoading\LoadAssignment::create([
            'faculty_load_id'  => $load->id,
            'user_id'          => $this->faculty->id,
            'school_year_id'   => $this->sy->id,
            'academic_term_id' => $this->term->id,
            'assignment_type'  => 'teaching',
            'subject_id'       => $this->subject->id,
            'load_units'       => 22.0,
            'created_by'       => $this->cid->id,
        ]);

        return $load->fresh(['assignments']);
    }

    private function createComputation(FacultyLoad $load, string $status): OverloadComputation
    {
        $annualRate = 468_000;
        $phtr       = ($annualRate / 1600) * 1.25;
        $pay        = $phtr * 3.0 * 18;

        return OverloadComputation::create([
            'user_id'            => $load->user_id,
            'faculty_load_id'    => $load->id,
            'school_year_id'     => $load->school_year_id,
            'academic_term_id'   => $load->academic_term_id,
            'annual_rate'        => $annualRate,
            'overload_units'     => $load->overload_units,
            'overload_hours'     => 3.0,
            'phtr'               => $phtr,
            'total_overload_pay' => $pay,
            'term_weeks'         => 18,
            'status'             => $status,
            'requested_by'       => $this->ocd->id,
            'approved_by'        => $status === 'approved' ? $this->ocd->id : null,
            'approved_at'        => $status === 'approved' ? now() : null,
        ]);
    }
}
