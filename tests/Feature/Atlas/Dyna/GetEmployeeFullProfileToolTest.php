<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\FacultyCommitteeAccomplishment;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\HR\DtrRecord;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveType;
use App\Models\ITJobRequest;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Pds;
use App\Models\PDSEligibility;
use App\Models\PDSFamilyBackground;
use App\Models\PDSPersonalInfo;
use App\Models\PDSVoluntaryWork;
use App\Models\PDSWorkExperience;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SALN\SalnRecord;
use App\Models\User;
use App\Models\WFHAttendance;
use App\Services\Atlas\Dyna\Tools\GetEmployeeFullProfileTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetEmployeeFullProfileToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_leave_section_when_permitted_and_omits_it_when_not(): void
    {
        $employee = User::factory()->create(['name' => 'Ana Reyes']);
        $leaveType = LeaveType::create(['code' => 'VL', 'name' => 'Vacation Leave']);
        LeaveCredit::create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'earned' => 15,
            'carried_over' => 0,
            'used' => 3,
            'forfeited' => 0,
            'monetized' => 0,
        ]);

        $permittedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage', 'hr.leave.credits.view']);
        $restrictedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage']);

        $withLeave = (new GetEmployeeFullProfileTool())->execute($permittedUser, ['identifier' => 'Ana Reyes']);
        $withoutLeave = (new GetEmployeeFullProfileTool())->execute($restrictedUser, ['identifier' => 'Ana Reyes']);

        $this->assertArrayHasKey('leave', $withLeave);
        $this->assertEquals(12.0, (float) $withLeave['leave']['credits'][0]['balance']);
        $this->assertArrayNotHasKey('leave', $withoutLeave);
    }

    public function test_returns_pds_personal_details_when_pds_is_viewable_and_omits_when_not(): void
    {
        $employee = User::factory()->create(['name' => 'Bea Santos']);
        $pds = Pds::create(['user_id' => $employee->id]);
        PDSPersonalInfo::create(['pds_id' => $pds->id, 'surname' => 'Santos', 'first_name' => 'Bea', 'date_of_birth' => '1990-05-01', 'civil_status' => 'Single', 'mobile_no' => '09171234567', 'tin_no' => '123-456-789']);
        PDSWorkExperience::create(['pds_id' => $pds->id, 'position' => 'Teacher I', 'agency' => 'PSHS-CRC', 'from_date' => '2020-01-01', 'to_date' => null, 'appointment_status' => 'Permanent', 'government_service' => true]);
        PDSEligibility::create(['pds_id' => $pds->id, 'eligibility' => 'LET', 'rating' => 85.5]);
        PDSVoluntaryWork::create(['pds_id' => $pds->id, 'organization' => 'Red Cross', 'hours' => 20]);
        PDSFamilyBackground::create(['pds_id' => $pds->id, 'spouse_first_name' => 'Carlo']);

        $permittedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage', 'pds.view_all']);
        $restrictedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage']);

        $withPds = (new GetEmployeeFullProfileTool())->execute($permittedUser, ['identifier' => 'Bea Santos']);
        $withoutPds = (new GetEmployeeFullProfileTool())->execute($restrictedUser, ['identifier' => 'Bea Santos']);

        $this->assertEquals('1990-05-01', $withPds['personal_info']['date_of_birth']);
        $this->assertEquals('09171234567', $withPds['personal_info']['mobile_no']);
        $this->assertCount(1, $withPds['work_experience']);
        $this->assertEquals('Teacher I', $withPds['work_experience'][0]['position']);
        $this->assertCount(1, $withPds['eligibility']);
        $this->assertCount(1, $withPds['voluntary_work']);
        $this->assertEquals('Carlo', $withPds['family_background']['spouse_first_name']);

        $this->assertArrayNotHasKey('personal_info', $withoutPds);
        $this->assertArrayNotHasKey('work_experience', $withoutPds);
        $this->assertArrayNotHasKey('eligibility', $withoutPds);
        $this->assertArrayNotHasKey('voluntary_work', $withoutPds);
        $this->assertArrayNotHasKey('family_background', $withoutPds);
    }

    public function test_returns_committee_accomplishment_ratings_alongside_membership(): void
    {
        $employee = User::factory()->create(['name' => 'Carlo Dizon']);
        $schoolYear = \App\Models\FacultyLoading\SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $term = \App\Models\FacultyLoading\AcademicTerm::create(['school_year_id' => $schoolYear->id, 'name' => '1st Semester']);
        $assignment = FacultyCommitteeAssignment::create([
            'user_id' => $employee->id,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'committee_name' => 'Research Committee',
            'role' => 'member',
            'status' => 'active',
        ]);
        $outcome = \App\Models\AgencyOutcome::create(['outcome' => 'Test Outcome']);
        $indicator = \App\Models\PerformanceIndicator::create(['agency_outcome_id' => $outcome->id, 'division_id' => \App\Models\Division::factory()->create()->id]);
        $plan = \App\Models\WorkDistributionPlan::create(['performance_indicator_id' => $indicator->id, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        FacultyCommitteeAccomplishment::create([
            'faculty_committee_assignment_id' => $assignment->id,
            'work_distribution_plan_id' => $plan->id,
            'accomplishment' => 'Reviewed 10 research proposals',
            'sup_quality' => 4.5,
            'sup_efficiency' => 4.0,
            'sup_timeliness' => 4.2,
            'sup_average' => 4.23,
        ]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage', 'faculty_loading.view']);

        $result = (new GetEmployeeFullProfileTool())->execute($user, ['identifier' => 'Carlo Dizon']);

        $this->assertEquals('Research Committee', $result['committees'][0]['committee_name']);
        $this->assertCount(1, $result['committee_accomplishments']);
        $this->assertEquals('Reviewed 10 research proposals', $result['committee_accomplishments'][0]['accomplishment']);
        $this->assertEquals(4.23, (float) $result['committee_accomplishments'][0]['sup_average']);
    }

    public function test_returns_gate_pass_history_when_dtr_permitted_and_omits_when_not(): void
    {
        $employee = User::factory()->create(['name' => 'Dina Reyes', 'badge_id' => '9001']);
        \DB::table('gatepass')->insert([
            'controlno' => 'GP-2026-0001',
            'badgeNumber' => 9001,
            'gatepass_type' => 'Official Business',
            'gatepass_timeout' => '08:00',
            'gatepass_timein' => '17:00',
            'gatepass_date' => '2026-08-01',
            'destination' => 'DepEd Caraga',
            'purpose' => 'Submit report',
            'date_time_approved' => '',
            'actual_timeout' => '',
            'actual_timein' => '',
            'time_consumed' => '',
            'status' => 'Approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permittedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage', 'hr.dtr.view']);
        $restrictedUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage']);

        $withGatePass = (new GetEmployeeFullProfileTool())->execute($permittedUser, ['identifier' => 'Dina Reyes']);
        $withoutGatePass = (new GetEmployeeFullProfileTool())->execute($restrictedUser, ['identifier' => 'Dina Reyes']);

        $this->assertCount(1, $withGatePass['gate_pass_recent']);
        $this->assertEquals('DepEd Caraga', $withGatePass['gate_pass_recent'][0]['destination']);
        $this->assertArrayNotHasKey('gate_pass_recent', $withoutGatePass);
    }

    public function test_returns_request_history_and_document_tracking_only_to_self_no_cross_employee_permission_exists(): void
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        $permission = Permission::firstOrCreate(['name' => 'hr.employees.manage'], ['module' => 'Atlas', 'description' => 'hr.employees.manage']);
        $role->permissions()->attach($permission);
        $employee = User::factory()->create(['name' => 'Elmer Cruz']);
        $employee->roles()->attach($role);

        $divisionChief = User::factory()->create();
        ITJobRequest::create([
            'itjr_no' => '2026-08-0001',
            'user_id' => $employee->id,
            'category' => 'Hardware',
            'title' => 'Laptop not booting',
            'description' => 'Laptop shows blue screen on startup.',
            'status' => 'For Approval of DC',
            'divisionchief_id' => $divisionChief->id,
        ]);
        $docType = DocumentType::create(['name' => 'Memo', 'code' => 'MEMO']);
        Document::create([
            'tracking_no' => 'DOC-2026-00001',
            'document_type_id' => $docType->id,
            'subject' => 'Budget Request',
            'created_by' => $employee->id,
            'current_holder_id' => $employee->id,
        ]);

        $otherHrUser = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage']);

        $selfResult = (new GetEmployeeFullProfileTool())->execute($employee, ['identifier' => 'Elmer Cruz']);
        $otherResult = (new GetEmployeeFullProfileTool())->execute($otherHrUser, ['identifier' => 'Elmer Cruz']);

        $this->assertCount(1, $selfResult['requests_recent']);
        $this->assertEquals('Laptop not booting', $selfResult['requests_recent'][0]['title']);
        $this->assertCount(1, $selfResult['documents_with_employee']);
        $this->assertEquals('Budget Request', $selfResult['documents_with_employee'][0]['subject']);

        $this->assertArrayNotHasKey('requests_recent', $otherResult);
        $this->assertArrayNotHasKey('documents_with_employee', $otherResult);
    }

    /**
     * Regression guard for a real prod bug: several sections extracted a
     * Carbon-cast date via raw property access inside ->map(), leaking a
     * Carbon object into the tool's return array. Mocked-Bedrock tests never
     * catch this — the real AWS SDK's Converse document-type validator does,
     * rejecting the whole request with `[...][json] is not a valid document
     * type`. Assert every leaf value is JSON-safe (scalar/null/array) so this
     * class of bug can't silently reappear.
     */
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $employee = User::factory()->create(['name' => 'Fiona Cruz']);
        $leaveType = LeaveType::create(['code' => 'SL', 'name' => 'Sick Leave']);
        LeaveApplication::create([
            'user_id' => $employee->id, 'leave_type_id' => $leaveType->id,
            'date_from' => '2026-08-01', 'date_to' => '2026-08-02', 'days_applied' => 2, 'status' => 'approved',
        ]);
        DtrRecord::create(['user_id' => $employee->id, 'work_date' => '2026-08-01', 'attendance_status' => 'Present']);
        SalnRecord::create(['user_id' => $employee->id, 'year' => 2026, 'as_of_date' => '2026-06-30', 'status' => 'Filed', 'filed_at' => now()]);
        WFHAttendance::create(['user_id' => $employee->id, 'date' => '2026-08-01', 'time_in' => now(), 'time_out' => now()]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'hr.employees.manage', 'hr.leave.credits.view', 'hr.dtr.view', 'saln.view_all', 'wfh.view']);

        $result = (new GetEmployeeFullProfileTool())->execute($user, ['identifier' => 'Fiona Cruz']);

        $this->assertNoNonScalarLeaves($result);
    }

    private function assertNoNonScalarLeaves(mixed $value, string $path = 'result'): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->assertNoNonScalarLeaves($item, "{$path}.{$key}");
            }

            return;
        }

        $this->assertTrue(
            is_scalar($value) || is_null($value),
            "Expected a JSON-safe scalar at {$path}, got ".(is_object($value) ? get_class($value) : gettype($value))
        );
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
