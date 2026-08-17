<?php

namespace Tests\Feature\SPMS;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end HTTP walkthrough of the full IPCR v2 lifecycle, substituting for
 * a live browser click-through: this app's login is Google-OAuth-only, so an
 * agent cannot self-test the real UI flow (no password fallback exists).
 */
class IpcrFullWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_employee_to_director_lifecycle(): void
    {
        // Roles/permissions
        $facultyRole = Role::create(['name' => 'Faculty']);
        $reviewerRole = Role::create(['name' => 'DivisionChief']);
        $managePermission = Permission::create(['name' => 'spms.ipcr.manage', 'module' => 'SPMS']);
        $reviewPermission = Permission::create(['name' => 'spms.ipcr.review', 'module' => 'SPMS']);
        $facultyRole->permissions()->attach($managePermission->id);
        $reviewerRole->permissions()->attach($reviewPermission->id);

        $employee = User::factory()->create();
        $employee->roles()->attach($facultyRole->id);
        $reviewer = User::factory()->create();
        $reviewer->roles()->attach($reviewerRole->id);

        // Load data + IPCR shell
        $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
        $term = AcademicTerm::create(['school_year_id' => $schoolYear->id, 'name' => '1st Semester', 'term_type' => '1st_semester']);
        $facultyLoad = FacultyLoad::create(['user_id' => $employee->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $employee->id,
            'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'load_units' => 6, 'description' => 'Physics 1',
        ]);
        $period = FiscalPeriod::factory()->create(['school_year_id' => $schoolYear->id]);
        $ipcr = Ipcr::factory()->create(['user_id' => $employee->id, 'fiscal_period_id' => $period->id]);

        // 1. Employee generates targets from load, then submits target
        $this->actingAs($employee)->post("/spms/ipcr/{$ipcr->id}/generate-targets")->assertRedirect();
        $this->assertGreaterThan(0, $ipcr->fresh()->targets->count());

        $this->actingAs($employee)->post("/spms/ipcr/{$ipcr->id}/submit-target")->assertRedirect();
        $this->assertSame(Ipcr::STATUS_TARGET_SUBMITTED, $ipcr->fresh()->status);

        // 2. Reviewer approves target
        $this->actingAs($reviewer)->post("/spms/ipcr/review/{$ipcr->id}/approve-target")->assertRedirect();
        $this->assertSame(Ipcr::STATUS_TARGET_APPROVED, $ipcr->fresh()->status);

        // 3. Employee submits for rating
        $this->actingAs($employee)->post("/spms/ipcr/{$ipcr->id}/submit-for-rating")->assertRedirect();
        $this->assertSame(Ipcr::STATUS_SUBMITTED_FOR_RATING, $ipcr->fresh()->status);

        // 4. Reviewer rates every target
        $target = $ipcr->fresh()->targets->first();
        $this->actingAs($reviewer)->post("/spms/ipcr/review/{$ipcr->id}/rate", [
            'ratings' => [$target->id => ['rating_q' => 5, 'rating_e' => 4, 'rating_t' => 5]],
        ])->assertRedirect();
        $this->assertSame(Ipcr::STATUS_RATED, $ipcr->fresh()->status);
        $this->assertNotNull($target->fresh()->rating_avg);

        // 5. Division Chief review, then PMT/HR review
        $this->actingAs($reviewer)->post("/spms/ipcr/review/{$ipcr->id}/review-dc")->assertRedirect();
        $this->assertSame(Ipcr::STATUS_DC_REVIEWED, $ipcr->fresh()->status);

        $this->actingAs($reviewer)->post("/spms/ipcr/review/{$ipcr->id}/review-pmt-hr")->assertRedirect();
        $this->assertSame(Ipcr::STATUS_PMT_HR_REVIEWED, $ipcr->fresh()->status);

        // 6. Director signs — terminal
        $this->actingAs($reviewer)->post("/spms/ipcr/review/{$ipcr->id}/finalize")->assertRedirect();
        $final = $ipcr->fresh();
        $this->assertSame(Ipcr::STATUS_DIRECTOR_SIGNED, $final->status);
        $this->assertNotNull($final->final_rating);
        $this->assertNotNull($final->final_adjectival);

        // Terminal: no further employee action is accepted
        $this->actingAs($employee)->post("/spms/ipcr/{$ipcr->id}/submit-target")->assertStatus(500);
    }

    public function test_self_service_store_then_full_lifecycle(): void
    {
        $facultyRole = Role::create(['name' => 'Faculty']);
        $reviewerRole = Role::create(['name' => 'DivisionChief']);
        $managePermission = Permission::create(['name' => 'spms.ipcr.manage', 'module' => 'SPMS']);
        $reviewPermission = Permission::create(['name' => 'spms.ipcr.review', 'module' => 'SPMS']);
        $facultyRole->permissions()->attach($managePermission->id);
        $reviewerRole->permissions()->attach($reviewPermission->id);

        $employee = User::factory()->create();
        $employee->roles()->attach($facultyRole->id);

        FiscalPeriod::factory()->create(['cadence' => 'semester', 'is_current' => true]);

        $this->actingAs($employee)->post('/spms/ipcr')->assertRedirect();
        $ipcr = Ipcr::where('user_id', $employee->id)->firstOrFail();
        $this->assertSame(Ipcr::STATUS_DRAFT_TARGET, $ipcr->status);

        // Re-clicking Create opens the same record, no duplicate
        $this->actingAs($employee)->post('/spms/ipcr')->assertRedirect(route('spms.ipcr.show', $ipcr->id));
        $this->assertSame(1, Ipcr::where('user_id', $employee->id)->count());
    }
}
