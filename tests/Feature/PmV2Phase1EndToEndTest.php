<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\PM2\EmployeeIpcrV2;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\PM2\OpcrTemplate;
use App\Models\PM2\OpcrTemplateItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PerformanceManagementV2\IpcrWorkflowServiceV2;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmV2Phase1EndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_phase1_vertical_slice_for_one_teaching_faculty_employee(): void
    {
        // ── Arrange: division, chief, teacher, school year, teaching load ──
        $chief = User::factory()->create();
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $chief->id]);
        $teacher = User::factory()->create(['division_id' => $division->id]);

        foreach (['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'PM V2', 'description' => $name]);
            $role = Role::firstOrCreate(['name' => "PmV2_{$name}"]);
            $role->permissions()->attach($permission->id);
            $teacher->roles()->attach($role->id);
        }

        $approvePermission = Permission::firstOrCreate(['name' => 'ipcr.v2.approve'], ['module' => 'PM V2', 'description' => 'ipcr.v2.approve']);
        $approverRole = Role::create(['name' => 'PmV2Approver']);
        $approverRole->permissions()->attach($approvePermission->id);
        $chief->roles()->attach($approverRole->id);

        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'is_current' => true]);
        $facultyLoad = FacultyLoad::create(['user_id' => $teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id]);
        $subject = Subject::create(['school_year_id' => $sy->id, 'code' => 'MATH1-'.uniqid(), 'name' => 'Math 1', 'grade_level' => 7]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 3,
        ]);

        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026, 'is_current' => true]);
        $template = OpcrTemplate::create(['ipcr_rating_period_v2_id' => $period->id, 'is_current' => true]);
        OpcrTemplateItem::create([
            'opcr_template_id' => $template->id, 'strategy_label' => 'Strategy 1',
            'output_outcome' => 'STEM secondary education strengthened', 'target' => '95%', 'weight_percent' => 30,
        ]);

        // ── Act 1: employee creates the IPCR ──
        $this->actingAs($teacher)->post(route('pm2.employee-ipcr.store'), [
            'rating_period_id' => $period->id, 'title' => 'Jul-Dec 2026 IPCR',
        ])->assertRedirect();

        $ipcr = EmployeeIpcrV2::where('user_id', $teacher->id)->firstOrFail();
        $this->assertEquals(IpcrWorkflowServiceV2::STATUS_NEW_TARGET, $ipcr->status);
        $strategicRow = $ipcr->rows()->where('function_type', 'strategic')->firstOrFail();
        $coreRow = $ipcr->rows()->where('function_type', 'core')->firstOrFail();

        // Strategic weight was already snapshotted from the template item (30).
        // This single-subject teacher has no Support row, so the division's
        // own weight distribution is set to Strategic 30 / Core 70 / Support 0
        // (still summing to 100) rather than the 30/50/20 default — Core's
        // row weight is set to match.
        $this->actingAs($teacher)->put(
            route('pm2.employee-ipcr.updateRowWeight', [$ipcr->id, $coreRow->id]),
            ['weight_percent' => 70]
        )->assertRedirect();

        // ── Act 2: supervisor approves targets ──
        \App\Models\IPCRWeightDistribution::create([
            'division_id' => $division->id, 'strategic' => 30, 'core' => 70, 'support' => 0,
        ]);

        $this->actingAs($chief)->post(route('pm2.supervisor-ipcr.approveTargets', $ipcr->id))
            ->assertRedirect();
        $this->assertEquals(IpcrWorkflowServiceV2::STATUS_TARGETS_APPROVED, $ipcr->fresh()->status);

        // ── Act 3: employee self-rates both rows ──
        foreach ([$strategicRow, $coreRow] as $row) {
            $this->actingAs($teacher)->put(route('pm2.employee-ipcr.selfRate', [$ipcr->id, $row->id]), [
                'accomplishment' => 'Delivered as planned.',
                'self_quality' => 5, 'self_efficiency' => 5, 'self_timeliness' => 5,
            ])->assertRedirect();
        }

        // ── Act 4: employee submits for rating ──
        $this->actingAs($teacher)->post(route('pm2.employee-ipcr.submitRating', $ipcr->id))->assertRedirect();
        $this->assertEquals(IpcrWorkflowServiceV2::STATUS_FOR_RATING, $ipcr->fresh()->status);

        // ── Act 5: supervisor rates both rows and marks rated ──
        foreach ([$strategicRow, $coreRow] as $row) {
            $this->actingAs($chief)->put(route('pm2.supervisor-ipcr.rateRow', [$ipcr->id, $row->id]), [
                'sup_quality' => 5, 'sup_efficiency' => 5, 'sup_timeliness' => 5,
            ])->assertRedirect();
        }
        $this->actingAs($chief)->post(route('pm2.supervisor-ipcr.markRated', $ipcr->id))->assertRedirect();

        $ipcr->refresh();
        $this->assertEquals(IpcrWorkflowServiceV2::STATUS_RATED, $ipcr->status);
        $this->assertEquals(5.0, (float) $ipcr->final_numeric_rating);
        $this->assertEquals('Outstanding', $ipcr->final_adjectival_rating);

        // ── Act 6: PDF downloads ──
        $pdfResponse = $this->actingAs($teacher)->get(route('pm2.employee-ipcr.pdf', $ipcr->id));
        $pdfResponse->assertOk();
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('Content-Type'));
    }
}
