<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\EmployeeIPCR;
use App\Models\IPCRCoachingSession;
use App\Models\IPCRRatingPeriod;
use App\Models\Permission;
use App\Models\PerformanceIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\PerformanceManagement\IPCRV1PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IPCRV1PdfTest extends TestCase
{
    use RefreshDatabase;

    private function userWithIpcrView(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'ipcr.view'],
            ['module' => 'IPCR', 'description' => 'ipcr.view'],
        );
        $role = Role::create(['name' => 'IpcrViewer_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function ipcrWithMultiPlanGroup(string $status = 'Rated & For PMT Review'): EmployeeIPCR
    {
        $period = IPCRRatingPeriod::create(['label' => 'FY 2026', 'year' => 2026]);
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        $child = AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id]);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $child->id, 'description' => 'Indicator 1', 'target' => '100%']);

        $planOne = WorkDistributionPlan::create(['performance_indicator_id' => $indicator->id, 'success_indicator' => 'First success indicator with a long wrapped description that would previously risk a page-break gap in the browser print CSS approach']);
        $planTwo = WorkDistributionPlan::create(['performance_indicator_id' => $indicator->id, 'success_indicator' => 'Second success indicator sharing the same rowspan group']);

        $employee = $this->userWithIpcrView();
        $ipcr = EmployeeIPCR::create([
            'user_id' => $employee->id,
            'rating_period_id' => $period->id,
            'rating_period' => $period->label,
            'title' => 'Test IPCR',
            'status' => $status,
        ]);
        $ipcr->plans()->attach($planOne->id, ['self_quality' => 3, 'self_efficiency' => 3, 'self_timeliness' => 3, 'sup_quality' => 5, 'sup_efficiency' => 4, 'sup_timeliness' => 5]);
        $ipcr->plans()->attach($planTwo->id, ['self_quality' => 3, 'self_efficiency' => 3, 'self_timeliness' => 3, 'sup_quality' => 4, 'sup_efficiency' => 4, 'sup_timeliness' => 4]);

        return $ipcr;
    }

    public function test_owner_can_stream_their_own_ipcr_v1_pdf(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup();

        $response = $this->actingAs($ipcr->user)->get(route('employee-ipcr.pdf', $ipcr->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_privileged_viewer_can_stream_another_employees_pdf(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup();
        $viewer = $this->userWithIpcrView();

        $this->actingAs($viewer)->get(route('employee-ipcr.pdf', $ipcr->id))->assertOk();
    }

    public function test_unrelated_user_without_permission_cannot_stream_the_pdf(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup();
        $unrelated = User::factory()->create();

        $this->actingAs($unrelated)->get(route('employee-ipcr.pdf', $ipcr->id))->assertForbidden();
    }

    public function test_html_contains_both_plans_in_the_rowspan_group_and_ratings(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup();
        $ipcr->load(['user', 'period', 'plans.performance_indicator.agencyOutcome.parent']);

        $html = (new IPCRV1PdfService())->renderHtml($ipcr);

        $this->assertStringContainsString('First success indicator', $html);
        $this->assertStringContainsString('Second success indicator', $html);
        $this->assertStringContainsString('rowspan="2"', $html); // Performance Indicator merged cell spans both plans
        $this->assertStringContainsString('Indicator 1', $html);
        // Admin Monitoring (AdminIPCRShow.vue) always shows the Self/DC split, regardless of status
        $this->assertStringContainsString('Self:', $html);
        $this->assertStringContainsString('DC: 5', $html);
    }

    public function test_html_always_shows_rating_summary_and_signature_block_regardless_of_status(): void
    {
        $period = IPCRRatingPeriod::create(['label' => 'FY 2026', 'year' => 2026]);
        $outcome = AgencyOutcome::create(['outcome' => 'Outcome X', 'function_type' => 'Core Functions']);
        $indicator = PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'description' => 'Indicator X', 'target' => '100%']);
        $plan = WorkDistributionPlan::create(['performance_indicator_id' => $indicator->id, 'success_indicator' => 'Pre-rating plan']);
        $employee = $this->userWithIpcrView();
        $ipcr = EmployeeIPCR::create([
            'user_id' => $employee->id,
            'rating_period_id' => $period->id,
            'rating_period' => $period->label,
            'title' => 'Test IPCR',
            'status' => 'New Target',
        ]);
        $ipcr->plans()->attach($plan->id);
        $ipcr->load(['user', 'period', 'plans.performance_indicator.agencyOutcome.parent']);

        $html = (new IPCRV1PdfService())->renderHtml($ipcr);

        $this->assertStringContainsString('Pre-rating plan', $html);
        // AdminIPCRShow.vue (IPCR Monitoring) has no status gating at all — the
        // Rating Summary table and signature block always render, unlike
        // EmployeeIPCRShow.vue. AdminIPCRShow.vue has no "Rating Summary" text
        // heading of its own, so assert on the table's actual structural markers.
        $this->assertStringContainsString('Overall Weighted Score', $html);
        $this->assertStringContainsString('TOTAL', $html);
        $this->assertStringContainsString('Adjectival Rating', $html);
        $this->assertStringContainsString('Discuss with', $html);
        $this->assertStringContainsString('Assessed by', $html);
        $this->assertStringContainsString('Final Rating by', $html);
    }

    public function test_html_main_table_header_has_ten_columns_matching_admin_ipcr_show(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup();
        $ipcr->load(['user', 'period', 'plans.performance_indicator.agencyOutcome.parent']);

        $html = (new IPCRV1PdfService())->renderHtml($ipcr);

        $this->assertStringContainsString('<th colspan="2" class="center">Output</th>', $html);
        $this->assertStringContainsString('<th>Success Indicators</th>', $html);
        $this->assertStringContainsString('<th>Actual Accomplishment</th>', $html);
        $this->assertStringContainsString('<th>Means of Verification</th>', $html);
        $this->assertStringContainsString('<th colspan="4" class="center">Rating</th>', $html);
        $this->assertStringContainsString('<th>Remarks</th>', $html);
        // Function-type banner row spans all 10 columns
        $this->assertStringContainsString('colspan="10" class="band"', $html);
    }

    public function test_self_rating_is_hidden_once_status_is_approved_by_pmt(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup('Approved by PMT');
        $ipcr->load(['user', 'period', 'plans.performance_indicator.agencyOutcome.parent']);

        $html = (new IPCRV1PdfService())->renderHtml($ipcr);

        $this->assertStringNotContainsString('Self:', $html);
        // The DC/supervisor value alone still renders in the rating cell
        $this->assertMatchesRegularExpression('/<td class="center">\s*5\s*<\/td>/', $html);
    }

    public function test_self_rating_is_hidden_once_status_is_director_signed(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup('Director Signed');
        $ipcr->load(['user', 'period', 'plans.performance_indicator.agencyOutcome.parent']);

        $html = (new IPCRV1PdfService())->renderHtml($ipcr);

        $this->assertStringNotContainsString('Self:', $html);
    }

    public function test_self_rating_still_shows_before_approved_by_pmt(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup('Submitted to PMT');
        $ipcr->load(['user', 'period', 'plans.performance_indicator.agencyOutcome.parent']);

        $html = (new IPCRV1PdfService())->renderHtml($ipcr);

        $this->assertStringContainsString('Self:', $html);
        $this->assertStringContainsString('DC: 5', $html);
    }

    public function test_coaching_journal_renders_on_its_own_page_when_sessions_exist(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup();
        IPCRCoachingSession::create([
            'employee_ipcr_id' => $ipcr->id,
            'created_by' => $ipcr->user_id,
            'activity_type' => 'coaching',
            'mechanism' => 'one_on_one',
            'meeting_date' => '2026-03-15',
            'channel_memo' => true,
            'remarks' => 'Discussed progress on Q1 targets.',
            'conducted_by_name' => 'Jane Supervisor',
            'conducted_at' => '2026-03-15',
            'noted_by_name' => 'John OCD',
            'noted_at' => '2026-03-16',
        ]);
        $ipcr->load(['user', 'period', 'plans.performance_indicator.agencyOutcome.parent', 'coachingSessions']);

        $html = (new IPCRV1PdfService())->renderHtml($ipcr);

        $this->assertStringContainsString('Performance Monitoring and Coaching Journal', $html);
        $this->assertStringContainsString('Jane Supervisor', $html);
        $this->assertStringContainsString('Discussed progress on Q1 targets.', $html);
        $this->assertStringContainsString('page-break-before: always', $html);

        // The page-break wrapper must come after the signature block, so the
        // journal is appended as new pages rather than interleaved with it.
        $signaturePos = strpos($html, 'Discuss with');
        $pageBreakPos = strpos($html, 'page-break-before: always');
        $this->assertNotFalse($signaturePos);
        $this->assertNotFalse($pageBreakPos);
        $this->assertGreaterThan($signaturePos, $pageBreakPos);
    }

    public function test_coaching_journal_section_omitted_entirely_when_no_sessions(): void
    {
        $ipcr = $this->ipcrWithMultiPlanGroup();
        $ipcr->load(['user', 'period', 'plans.performance_indicator.agencyOutcome.parent', 'coachingSessions']);

        $html = (new IPCRV1PdfService())->renderHtml($ipcr);

        $this->assertStringNotContainsString('Performance Monitoring and Coaching Journal', $html);
        $this->assertStringNotContainsString('page-break-before: always', $html);
    }
}
