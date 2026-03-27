<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\OverloadComputation;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\SstPosition;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 — Reports module tests.
 *
 * Covers:
 *   - GET /faculty-loading/reports/loads           — Inertia page + filters
 *   - GET /faculty-loading/reports/loads/export    — CSV download
 *   - GET /faculty-loading/reports/overload-pay    — Inertia page + filters
 *   - GET /faculty-loading/reports/overload-pay/export — CSV download
 *   - Permission gate (403 without faculty_loading.reports)
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User        $reporter;
    private SchoolYear  $sy;
    private AcademicTerm $term;
    private SstPosition $position;
    private FacultyLoad $load;

    protected function setUp(): void
    {
        parent::setUp();

        // User with faculty_loading.reports permission
        $role = Role::create(['name' => 'TestReporter_' . uniqid()]);
        $perm = Permission::firstOrCreate(
            ['name' => 'faculty_loading.reports'],
            ['module' => 'FacultyLoading', 'description' => 'View reports']
        );
        $role->permissions()->attach($perm->id);
        $this->reporter = User::factory()->create(['email_verified_at' => now()]);
        $this->reporter->roles()->attach($role->id);

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

        $faculty = User::factory()->create([
            'email_verified_at' => now(),
            'sst_position_id'   => $this->position->id,
        ]);

        $this->load = FacultyLoad::create([
            'user_id'             => $faculty->id,
            'school_year_id'      => $this->sy->id,
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

    // ═══════════════════════════════════════════════════════════════════════════
    // Faculty Load Report
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_loads_report_renders_for_authorised_user(): void
    {
        $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.loads'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/Reports/Loads')
                ->has('loads')
                ->has('terms')
                ->has('summary')
            );
    }

    public function test_loads_report_returns_403_without_permission(): void
    {
        $noPerms = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($noPerms)
            ->get(route('faculty-loading.reports.loads'))
            ->assertForbidden();
    }

    public function test_loads_report_filters_by_term(): void
    {
        $otherTerm = AcademicTerm::create([
            'school_year_id' => $this->sy->id,
            'name'           => '2nd Semester',
            'term_type'      => '2nd_semester',
            'start_date'     => '2026-01-01',
            'end_date'       => '2026-05-31',
            'is_current'     => false,
        ]);

        // Load in other term
        FacultyLoad::create([
            'user_id'             => $this->load->user_id,
            'school_year_id'      => $this->sy->id,
            'academic_term_id'    => $otherTerm->id,
            'teaching_units'      => 18.00,
            'research_units'      => 0,
            'admin_units'         => 0,
            'cocurricular_units'  => 0,
            'committee_units'     => 0,
            'total_units'         => 18.00,
            'full_load_threshold' => 18.00,
            'load_status'         => 'full_load',
            'overload_approved'   => false,
        ]);

        $response = $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.loads', ['term_id' => $this->term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('loads', 1));

        $this->assertNotNull($response);
    }

    public function test_loads_report_filters_by_status(): void
    {
        $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.loads', ['status' => 'overload']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('loads', 1));

        $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.loads', ['status' => 'underload']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('loads', 0));
    }

    public function test_loads_report_summary_counts_are_correct(): void
    {
        $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.loads', ['term_id' => $this->term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.total',    1)
                ->where('summary.overload', 1)
                ->where('summary.full_load', 0)
                ->where('summary.underload', 0)
            );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Loads CSV export
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_loads_export_returns_csv(): void
    {
        $response = $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.loads.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_loads_export_contains_header_row(): void
    {
        $content = $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.loads.export'))
            ->streamedContent();

        $this->assertStringContainsString('Faculty Name', $content);
        $this->assertStringContainsString('Total Units', $content);
        $this->assertStringContainsString('Overload Units', $content);
    }

    public function test_loads_export_contains_data_row(): void
    {
        $content = $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.loads.export', ['term_id' => $this->term->id]))
            ->streamedContent();

        // Status column
        $this->assertStringContainsString('overload', $content);
    }

    public function test_loads_export_returns_403_without_permission(): void
    {
        $noPerms = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($noPerms)
            ->get(route('faculty-loading.reports.loads.export'))
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Overload Pay Report
    // ═══════════════════════════════════════════════════════════════════════════

    private function seedComputation(string $status = 'approved'): OverloadComputation
    {
        return OverloadComputation::create([
            'user_id'            => $this->load->user_id,
            'faculty_load_id'    => $this->load->id,
            'school_year_id'     => $this->sy->id,
            'academic_term_id'   => $this->term->id,
            'annual_rate'        => 468_000,
            'overload_units'     => 4,
            'overload_hours'     => 3,
            'phtr'               => 365.625,
            'total_overload_pay' => 19_743.75,
            'term_weeks'         => 18,
            'status'             => $status,
            'requested_by'       => null,
        ]);
    }

    public function test_overload_pay_report_renders_for_authorised_user(): void
    {
        $this->seedComputation();

        $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.overload-pay'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/Reports/OverloadPay')
                ->has('computations', 1)
                ->has('grandTotal')
            );
    }

    public function test_overload_pay_report_returns_403_without_permission(): void
    {
        $noPerms = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($noPerms)
            ->get(route('faculty-loading.reports.overload-pay'))
            ->assertForbidden();
    }

    public function test_overload_pay_report_filters_by_status(): void
    {
        $this->seedComputation('approved');

        $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.overload-pay', ['status' => 'approved']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('computations', 1));

        $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.overload-pay', ['status' => 'paid']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('computations', 0));
    }

    public function test_overload_pay_report_grand_total_sums_correctly(): void
    {
        $this->seedComputation('approved');

        $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.overload-pay', ['term_id' => $this->term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('grandTotal', 19743.75)
            );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Overload Pay CSV export
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_overload_pay_export_returns_csv(): void
    {
        $this->seedComputation();

        $response = $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.overload-pay.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_overload_pay_export_contains_grand_total_row(): void
    {
        $this->seedComputation();

        $content = $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.overload-pay.export', ['term_id' => $this->term->id]))
            ->streamedContent();

        $this->assertStringContainsString('TOTAL', $content);
        $this->assertStringContainsString('19,743.75', $content);
    }

    public function test_overload_pay_export_contains_header_row(): void
    {
        $content = $this->actingAs($this->reporter)
            ->get(route('faculty-loading.reports.overload-pay.export'))
            ->streamedContent();

        $this->assertStringContainsString('Faculty Name', $content);
        $this->assertStringContainsString('PHTR', $content);
        $this->assertStringContainsString('Total Overload Pay', $content);
    }

    public function test_overload_pay_export_returns_403_without_permission(): void
    {
        $noPerms = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($noPerms)
            ->get(route('faculty-loading.reports.overload-pay.export'))
            ->assertForbidden();
    }
}
