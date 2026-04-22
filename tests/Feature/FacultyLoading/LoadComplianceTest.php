<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\SstPosition;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the compliance-checking additions to LoadComputationService:
 *
 *   - checkCommitteeCompliance()  — Per BOT Resolution 2025-05-80:
 *       every faculty must have at least 1 committee per term.
 *
 *   - validatePositionLoad()      — Per PSHS practice:
 *       SST positions have a minimum teaching units requirement.
 *
 *   - validateAll() integration   — Both checks surface correctly in
 *       the comprehensive validation summary.
 */
class LoadComplianceTest extends TestCase
{
    use RefreshDatabase;

    private LoadComputationService $svc;
    private User         $faculty;
    private SchoolYear   $schoolYear;
    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = new LoadComputationService();

        $this->faculty    = User::factory()->create(['email_verified_at' => now()]);
        $this->schoolYear = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $this->term       = AcademicTerm::create(['school_year_id' => $this->schoolYear->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeLoad(array $overrides = []): FacultyLoad
    {
        return FacultyLoad::create(array_merge([
            'user_id'             => $this->faculty->id,
            'school_year_id'      => $this->schoolYear->id,
            'academic_term_id'    => $this->term->id,
            'teaching_units'      => 12.00,
            'research_units'      => 2.00,
            'admin_units'         => 2.00,
            'cocurricular_units'  => 1.00,
            'committee_units'     => 1.00,
            'total_units'         => 18.00,
            'load_status'         => 'full_load',
            'full_load_threshold' => 18.00,
            'overload_approved'   => false,
        ], $overrides));
    }

    private function makePosition(array $overrides = []): SstPosition
    {
        return SstPosition::create(array_merge([
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
        ], $overrides));
    }

    private function addCommittee(string $role = 'member', string $status = 'active'): FacultyCommitteeAssignment
    {
        return FacultyCommitteeAssignment::create([
            'user_id'          => $this->faculty->id,
            'school_year_id'   => $this->schoolYear->id,
            'academic_term_id' => $this->term->id,
            'committee_id'     => null,
            'committee_name'   => 'Academic Committee',
            'role'             => $role,
            'load_units'       => 0.50,
            'status'           => $status,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // checkCommitteeCompliance()
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_flags_non_compliance_when_faculty_has_no_committee(): void
    {
        $result = $this->svc->checkCommitteeCompliance(
            $this->faculty->id, $this->term->id
        );

        $this->assertFalse($result['compliant']);
        $this->assertSame(0, $result['count']);
        $this->assertFalse($result['has_chair']);
        $this->assertStringContainsString('no active committee', $result['message']);
    }

    /** @test */
    public function it_marks_compliant_when_faculty_has_one_committee(): void
    {
        $this->addCommittee('member');

        $result = $this->svc->checkCommitteeCompliance(
            $this->faculty->id, $this->term->id
        );

        $this->assertTrue($result['compliant']);
        $this->assertSame(1, $result['count']);
        $this->assertFalse($result['has_chair']);
    }

    /** @test */
    public function it_detects_chairpersonship(): void
    {
        $this->addCommittee('chairperson');

        $result = $this->svc->checkCommitteeCompliance(
            $this->faculty->id, $this->term->id
        );

        $this->assertTrue($result['compliant']);
        $this->assertTrue($result['has_chair']);
    }

    /** @test */
    public function it_detects_co_chair_as_chairpersonship(): void
    {
        $this->addCommittee('co_chair');

        $result = $this->svc->checkCommitteeCompliance(
            $this->faculty->id, $this->term->id
        );

        $this->assertTrue($result['has_chair']);
    }

    /** @test */
    public function it_ignores_inactive_committee_assignments(): void
    {
        $this->addCommittee('member', 'inactive');

        $result = $this->svc->checkCommitteeCompliance(
            $this->faculty->id, $this->term->id
        );

        // Inactive assignment should not satisfy compliance
        $this->assertFalse($result['compliant']);
        $this->assertSame(0, $result['count']);
    }

    /** @test */
    public function it_counts_multiple_committee_assignments(): void
    {
        $this->addCommittee('member');
        $this->addCommittee('member');
        $this->addCommittee('chairperson');

        $result = $this->svc->checkCommitteeCompliance(
            $this->faculty->id, $this->term->id
        );

        $this->assertTrue($result['compliant']);
        $this->assertSame(3, $result['count']);
        $this->assertTrue($result['has_chair']);
    }

    /** @test */
    public function it_scopes_compliance_check_to_the_given_term(): void
    {
        // Committee in a different term
        $otherTerm = AcademicTerm::create(['school_year_id' => $this->schoolYear->id, 'name' => '2nd Semester', 'term_type' => '2nd_semester', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'is_current' => false]);

        FacultyCommitteeAssignment::create([
            'user_id'          => $this->faculty->id,
            'school_year_id'   => $this->schoolYear->id,
            'academic_term_id' => $otherTerm->id,   // <-- different term
            'committee_id'     => null,
            'committee_name'   => 'Academic Committee',
            'role'             => 'member',
            'load_units'       => 0.50,
            'status'           => 'active',
        ]);

        // Check compliance for the original term — should be non-compliant
        $result = $this->svc->checkCommitteeCompliance(
            $this->faculty->id, $this->term->id
        );

        $this->assertFalse($result['compliant']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // validatePositionLoad()
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function it_passes_when_teaching_units_meet_minimum(): void
    {
        $position = $this->makePosition(['min_teaching_units' => 6.00]);

        $result = $this->svc->validatePositionLoad(9.00, $position);

        $this->assertTrue($result['valid']);
        $this->assertSame(9.0,  $result['current']);
        $this->assertSame(6.0,  $result['min']);
        $this->assertSame('SST-III', $result['position']);
    }

    /** @test */
    public function it_passes_when_teaching_units_exactly_equal_minimum(): void
    {
        $position = $this->makePosition(['min_teaching_units' => 6.00]);

        $result = $this->svc->validatePositionLoad(6.00, $position);

        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function it_fails_when_teaching_units_are_below_minimum(): void
    {
        $position = $this->makePosition(['min_teaching_units' => 9.00]);

        $result = $this->svc->validatePositionLoad(4.00, $position);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('below the minimum', $result['message']);
    }

    /** @test */
    public function it_uses_the_positions_own_minimum_not_a_global_constant(): void
    {
        $highMinPosition = $this->makePosition(['code' => 'SST-I', 'min_teaching_units' => 9.00]);
        $lowMinPosition  = $this->makePosition(['code' => 'SST-V', 'name' => 'SST V', 'min_teaching_units' => 6.00]);

        // 8 units passes SST-I's minimum but not SST-V if min were higher
        $resultHigh = $this->svc->validatePositionLoad(8.00, $highMinPosition);
        $resultLow  = $this->svc->validatePositionLoad(8.00, $lowMinPosition);

        $this->assertFalse($resultHigh['valid']);  // 8 < 9 (SST-I min)
        $this->assertTrue($resultLow['valid']);    // 8 >= 6 (SST-V min)
    }

    /** @test */
    public function it_includes_position_code_in_validation_message(): void
    {
        $position = $this->makePosition(['code' => 'SST-III']);

        $result = $this->svc->validatePositionLoad(4.00, $position);

        $this->assertStringContainsString('SST-III', $result['message']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // validateAll() — integration with new checks
    // ═══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function validate_all_includes_committee_check_in_result(): void
    {
        $load = $this->makeLoad();

        $result = $this->svc->validateAll($load);

        $this->assertArrayHasKey('committee_check', $result);
        $this->assertArrayHasKey('compliant', $result['committee_check']);
    }

    /** @test */
    public function validate_all_warns_when_faculty_has_no_committee(): void
    {
        $load = $this->makeLoad();

        $result = $this->svc->validateAll($load);

        $committeeWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'committee'));
        $this->assertNotEmpty($committeeWarnings);
    }

    /** @test */
    public function validate_all_does_not_warn_about_committee_when_compliant(): void
    {
        $this->addCommittee('member');
        $load = $this->makeLoad();

        $result = $this->svc->validateAll($load);

        $committeeWarnings = array_filter($result['warnings'], fn ($w) =>
            str_contains($w, 'no active committee')
        );
        $this->assertEmpty($committeeWarnings);
    }

    /** @test */
    public function validate_all_includes_position_check_when_position_provided(): void
    {
        $this->addCommittee('member');
        $position = $this->makePosition(['min_teaching_units' => 6.00]);
        $load     = $this->makeLoad(['teaching_units' => 12.00]);

        $result = $this->svc->validateAll($load, $position);

        $this->assertNotNull($result['position_check']);
        $this->assertTrue($result['position_check']['valid']);
    }

    /** @test */
    public function validate_all_warns_when_teaching_units_below_position_minimum(): void
    {
        $this->addCommittee('member');
        $position = $this->makePosition(['min_teaching_units' => 9.00]);
        $load     = $this->makeLoad(['teaching_units' => 4.00]); // below min

        $result = $this->svc->validateAll($load, $position);

        $positionWarnings = array_filter($result['warnings'], fn ($w) => str_contains($w, 'below the minimum'));
        $this->assertNotEmpty($positionWarnings);
    }

    /** @test */
    public function validate_all_position_check_is_null_when_no_position_given(): void
    {
        $this->addCommittee('member');
        $load = $this->makeLoad();

        $result = $this->svc->validateAll($load);

        $this->assertNull($result['position_check']);
    }

    /** @test */
    public function validate_all_result_includes_all_expected_keys(): void
    {
        $load = $this->makeLoad();

        $result = $this->svc->validateAll($load);

        foreach (['valid', 'errors', 'warnings', 'admin_check', 'research_check',
                  'position_check', 'committee_check', 'load_status',
                  'total_units', 'overload_units'] as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }
}
