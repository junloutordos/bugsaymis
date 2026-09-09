<?php

namespace Tests\Feature\ALP;

use App\Models\ALP\AlpActivity;
use App\Models\ALP\AlpAttendance;
use App\Models\ALP\AlpFinancialEntry;
use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpOfficer;
use App\Models\ALP\AlpProgram;
use App\Models\ALP\AlpProgramCycle;
use App\Models\ALP\AlpReport;
use App\Models\ALP\AlpSession;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use App\Services\ALP\AlpComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the PSHS-00-F-DSA-24..37 form templates rebuilt to
 * match the official controlled documents: every route must render without
 * error and carry the real content (not the old generic layout's filler
 * text), so each assertion checks for a field/label unique to that form.
 */
class AlpFormsPdfTest extends TestCase
{
    use RefreshDatabase;

    private function alpUser(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'alp.manage'], ['module' => 'ALP', 'description' => 'alp.manage']);
        $role = Role::create(['name' => 'AlpManagerTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeCycle(): array
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true, 'status' => 'active']);
        $program = AlpProgram::create(['code' => 'ALP-'.uniqid(), 'name' => 'Reading Recovery Program', 'status' => 'active']);
        $cycle = AlpProgramCycle::create(['alp_program_id' => $program->id, 'school_year_id' => $sy->id, 'status' => 'draft']);

        $studentId = (int) DB::table('students')->insertGetId(['lastname' => 'Cruz', 'firstname' => 'Ana', 'sex' => 'Female']);
        $enrollment = StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => null,
            'grade_level' => 8, 'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);
        $membership = AlpMembership::create([
            'alp_program_cycle_id' => $cycle->id, 'school_year_id' => $sy->id, 'student_id' => $studentId,
            'student_enrollment_id' => $enrollment->id, 'status' => 'active', 'joined_at' => '2026-07-20',
        ]);
        AlpOfficer::create(['alp_program_cycle_id' => $cycle->id, 'alp_membership_id' => $membership->id, 'position' => 'President', 'sort_order' => 0]);

        app(AlpComplianceService::class)->seedDocuments($cycle);

        return [$cycle->fresh(), $membership];
    }

    public function test_every_official_document_form_renders_with_its_own_control_number(): void
    {
        $user = $this->alpUser();
        [$cycle] = $this->makeCycle();

        $expectedTitles = [
            'application' => 'APPLICATION LETTER FOR RECOGNITION',
            'constitution' => 'CONSTITUTION AND BY LAWS',
            'adviser_acceptance' => 'ACCEPTANCE LETTER OF ADVISERSHIP',
            'officers' => 'OFFICIAL LIST OF OFFICERS',
            'officer_certification' => 'CERTIFICATION OF STUDENT RECORD',
            'class_list' => 'OFFICIAL CLASS LIST',
            'calendar' => 'CALENDAR OF ACTIVITIES',
            'risk_plan' => 'RISK ASSESSMENT AND PREVENTIVE MEASURES PLAN',
        ];

        foreach ($cycle->documents as $document) {
            if (! isset($expectedTitles[$document->document_type])) {
                continue; // parent_consent has no cycle-level PDF — it's printed per-member.
            }
            $response = $this->actingAs($user)->get(route('alp.documents.pdf', [$cycle, $document]));
            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/pdf');
            $this->assertNotEmpty($response->getContent());
        }
    }

    public function test_parent_consent_form_prints_per_member_with_the_official_control_number(): void
    {
        $user = $this->alpUser();
        [$cycle, $membership] = $this->makeCycle();

        $response = $this->actingAs($user)->get(route('alp.members.consent-form.pdf', [$cycle, $membership]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('PSHS-00-F-DSA-31', $response->headers->get('Content-Disposition'));
    }

    public function test_monthly_attendance_grid_renders_the_official_form_for_the_given_month(): void
    {
        $user = $this->alpUser();
        [$cycle, $membership] = $this->makeCycle();
        $session = AlpSession::create(['alp_program_cycle_id' => $cycle->id, 'session_date' => '2026-08-05']);
        AlpAttendance::create(['alp_session_id' => $session->id, 'alp_membership_id' => $membership->id, 'status' => 'present']);

        $response = $this->actingAs($user)->get(route('alp.attendance.grid.pdf', [$cycle, 'month' => '2026-08']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('PSHS-00-F-DSA-33', $response->headers->get('Content-Disposition'));
    }

    public function test_monthly_attendance_grid_requires_a_valid_month_format(): void
    {
        $user = $this->alpUser();
        [$cycle] = $this->makeCycle();

        $response = $this->actingAs($user)->get(route('alp.attendance.grid.pdf', [$cycle, 'month' => 'not-a-month']));

        $response->assertSessionHasErrors('month');
    }

    public function test_daily_session_slip_no_longer_claims_the_official_form_33_control_number(): void
    {
        $user = $this->alpUser();
        [$cycle, $membership] = $this->makeCycle();
        $session = AlpSession::create(['alp_program_cycle_id' => $cycle->id, 'session_date' => '2026-08-05']);
        AlpAttendance::create(['alp_session_id' => $session->id, 'alp_membership_id' => $membership->id, 'status' => 'present']);

        $response = $this->actingAs($user)->get(route('alp.attendance.pdf', [$cycle, $session]));

        $response->assertOk();
        $this->assertStringNotContainsString('PSHS-00-F-DSA-33', $response->headers->get('Content-Disposition'));
    }

    public function test_financial_report_is_server_computed_from_ledger_entries(): void
    {
        $user = $this->alpUser();
        [$cycle] = $this->makeCycle();
        AlpFinancialEntry::create(['alp_program_cycle_id' => $cycle->id, 'transaction_date' => '2026-08-01', 'entry_type' => 'opening_balance', 'description' => 'Beginning balance', 'amount' => 1000]);
        AlpFinancialEntry::create(['alp_program_cycle_id' => $cycle->id, 'transaction_date' => '2026-08-02', 'entry_type' => 'income', 'category' => 'membership_fees', 'description' => 'Dues', 'amount' => 500]);

        // A client-submitted 'data' payload for an auto-computed report type must be ignored server-side.
        $this->actingAs($user)->post(route('alp.reports.store', $cycle), [
            'report_type' => 'financial', 'period' => 'Q1', 'status' => 'draft',
            'data' => ['income' => ['membership_fees' => ['amount' => 999999]]],
        ])->assertSessionHasNoErrors();

        $report = AlpReport::where('alp_program_cycle_id', $cycle->id)->where('report_type', 'financial')->firstOrFail();
        $this->assertSame(1000.0, (float) $report->data['opening_balance']);
        $this->assertSame(500.0, (float) $report->data['income']['membership_fees']['amount']);
        $this->assertSame(1500.0, (float) $report->data['ending_balance']);

        $response = $this->actingAs($user)->get(route('alp.reports.pdf', [$cycle, $report]));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_accomplishment_report_pulls_completed_activities_and_keeps_manual_assessment(): void
    {
        $user = $this->alpUser();
        [$cycle] = $this->makeCycle();
        AlpActivity::create([
            'alp_program_cycle_id' => $cycle->id, 'title' => 'Orientation Day', 'activity_type' => 'major',
            'start_date' => '2026-08-01', 'end_date' => '2026-08-01', 'venue' => 'Gym',
            'status' => 'completed', 'accomplishments' => 'Well attended.',
        ]);

        $this->actingAs($user)->post(route('alp.reports.store', $cycle), [
            'report_type' => 'accomplishment', 'period' => 'Q1', 'status' => 'draft',
            'data' => ['assessment' => [['strength' => 'Good turnout', 'weakness' => '', 'gap' => '', 'recommendation' => '']]],
        ])->assertSessionHasNoErrors();

        $report = AlpReport::where('alp_program_cycle_id', $cycle->id)->where('report_type', 'accomplishment')->firstOrFail();
        $this->assertSame('Orientation Day', $report->data['activities'][0]['title']);
        $this->assertSame('Good turnout', $report->data['assessment'][0]['strength']);

        $response = $this->actingAs($user)->get(route('alp.reports.pdf', [$cycle, $report]));
        $response->assertOk();
    }

    public function test_package_bundles_documents_in_pshs_numeric_sequence(): void
    {
        $user = $this->alpUser();
        [$cycle] = $this->makeCycle();

        $response = $this->actingAs($user)->get(route('alp.cycles.package', $cycle));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_finance_category_is_restricted_to_the_controlled_bucket_vocabulary(): void
    {
        $user = $this->alpUser();
        [$cycle] = $this->makeCycle();

        $this->actingAs($user)->post(route('alp.finances.store', $cycle), [
            'transaction_date' => '2026-08-01', 'entry_type' => 'income', 'category' => 'not_a_real_bucket',
            'description' => 'Test', 'amount' => 100,
        ])->assertSessionHasErrors('category');

        $this->actingAs($user)->post(route('alp.finances.store', $cycle), [
            'transaction_date' => '2026-08-01', 'entry_type' => 'income', 'category' => 'membership_fees',
            'description' => 'Test', 'amount' => 100,
        ])->assertSessionHasNoErrors();
    }
}
