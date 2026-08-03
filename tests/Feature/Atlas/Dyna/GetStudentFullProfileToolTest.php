<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\Consultation;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\GuidanceConsultation;
use App\Models\HomeroomAttendance\AttendanceDate;
use App\Models\HomeroomAttendance\AttendanceRecord;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\ResidenceHall\RhIntern;
use App\Models\ResidenceHall\RhRoom;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetStudentFullProfileTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult;
use Tests\TestCase;

class GetStudentFullProfileToolTest extends TestCase
{
    use AssertsJsonSafeToolResult;
    use RefreshDatabase;

    public function test_returns_enrollment_history_and_omits_discipline_without_access(): void
    {
        $lastname = 'FullProfileLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);

        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        StudentEnrollment::create(['student_id' => $studentId, 'school_year_id' => $schoolYear->id, 'section_id' => 3, 'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view']);

        $result = app(GetStudentFullProfileTool::class)->execute($user, ['identifier' => $lastname]);

        $this->assertCount(1, $result['enrollment_history']);
        $this->assertEquals(8, $result['enrollment_history'][0]['grade_level']);
        $this->assertArrayNotHasKey('discipline', $result);
    }

    public function test_returns_only_name_by_default_and_full_pii_when_records_export_permitted(): void
    {
        $lastname = 'PersonalInfoLookup'.uniqid();
        \DB::table('students')->insertGetId([
            'lastname' => $lastname,
            'firstname' => 'Test',
            'birthday' => '2010-03-15',
            'sex' => 'Female',
            'bloodtype' => 'O+',
            'contactno1' => '09171234567',
            'houseno' => '123',
            'barangay' => 'Ampayon',
            'lrn' => '123456789012',
        ]);

        $restrictedUser = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view']);
        $exportUser = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view', 'students.records.export']);

        $restricted = app(GetStudentFullProfileTool::class)->execute($restrictedUser, ['identifier' => $lastname]);
        $full = app(GetStudentFullProfileTool::class)->execute($exportUser, ['identifier' => $lastname]);

        $this->assertEquals('Test  '.$lastname, $restricted['personal_info']['name']);
        $this->assertArrayNotHasKey('birthday', $restricted['personal_info']);
        $this->assertArrayNotHasKey('address', $restricted['personal_info']);

        $this->assertEquals('2010-03-15', $full['personal_info']['birthday']);
        $this->assertEquals('Female', $full['personal_info']['sex']);
        $this->assertEquals('O+', $full['personal_info']['blood_type']);
        $this->assertEquals('123456789012', $full['personal_info']['lrn']);
    }

    public function test_returns_health_records_when_permitted_and_omits_when_not(): void
    {
        $lastname = 'HealthLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);
        Consultation::create([
            'student_id' => $studentId,
            'reason' => 'Fever',
            'status' => 'Completed',
        ]);

        $permittedUser = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view', 'health.manage']);
        $restrictedUser = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view']);

        $withHealth = app(GetStudentFullProfileTool::class)->execute($permittedUser, ['identifier' => $lastname]);
        $withoutHealth = app(GetStudentFullProfileTool::class)->execute($restrictedUser, ['identifier' => $lastname]);

        $this->assertCount(1, $withHealth['health_records']);
        $this->assertEquals('Fever', $withHealth['health_records'][0]['reason']);
        $this->assertArrayNotHasKey('health_records', $withoutHealth);
    }

    public function test_returns_guidance_records_when_permitted_and_omits_when_not(): void
    {
        $lastname = 'GuidanceLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);
        GuidanceConsultation::create([
            'requestor_id' => $studentId,
            'concern' => 'Academic difficulty',
            'status' => 'pending',
        ]);

        $permittedUser = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view', 'guidance.view']);
        $restrictedUser = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view']);

        $withGuidance = app(GetStudentFullProfileTool::class)->execute($permittedUser, ['identifier' => $lastname]);
        $withoutGuidance = app(GetStudentFullProfileTool::class)->execute($restrictedUser, ['identifier' => $lastname]);

        $this->assertCount(1, $withGuidance['guidance_records']);
        $this->assertEquals('Academic difficulty', $withGuidance['guidance_records'][0]['concern']);
        $this->assertArrayNotHasKey('guidance_records', $withoutGuidance);
    }

    public function test_returns_ams_participation_and_residence_hall_when_permitted(): void
    {
        $lastname = 'AmsRhLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);

        $activity = Activity::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Science Fair 2026',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
        ]);
        ActivityStudentAttendance::create([
            'activity_id' => $activity->id,
            'participant_id' => $studentId,
            'attended' => true,
            'hours_attended' => 3.5,
        ]);

        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $room = RhRoom::create(['residence_hall' => 'BRH', 'room_number' => '101']);
        RhIntern::create([
            'student_id' => $studentId,
            'school_year_id' => $schoolYear->id,
            'rh_room_id' => $room->id,
            'bed_number' => 1,
            'status' => 'active',
        ]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view', 'activities.view_all', 'rh.interns.view']);

        $result = app(GetStudentFullProfileTool::class)->execute($user, ['identifier' => $lastname]);

        $this->assertCount(1, $result['ams_participation']);
        $this->assertEquals('Science Fair 2026', $result['ams_participation'][0]['activity_title']);
        $this->assertEquals('active', $result['residence_hall']['status']);
        $this->assertEquals('BRH', $result['residence_hall']['residence_hall']);
    }

    /**
     * Regression guard for a real prod bug: attendance_homeroom and
     * residence_hall extracted Carbon-cast dates via raw property access,
     * leaking Carbon objects into the tool's return array. Mocked-Bedrock
     * tests never catch this — only the real AWS SDK's Converse document-type
     * validator does, rejecting the whole request. Assert every leaf value is
     * JSON-safe so this class of bug can't silently reappear.
     */
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $lastname = 'JsonSafeLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);

        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $taker = User::factory()->create();
        $attendanceDate = AttendanceDate::create(['section_id' => 1, 'school_year_id' => $schoolYear->id, 'date' => '2026-08-01', 'taken_by' => $taker->id]);
        AttendanceRecord::create(['homeroom_attendance_date_id' => $attendanceDate->id, 'student_id' => $studentId, 'status' => 'present']);

        $room = RhRoom::create(['residence_hall' => 'BRH', 'room_number' => '102']);
        RhIntern::create(['student_id' => $studentId, 'school_year_id' => $schoolYear->id, 'rh_room_id' => $room->id, 'status' => 'active', 'check_in_date' => '2026-06-01']);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view', 'homeroom-attendance.admin', 'rh.interns.view']);

        $result = app(GetStudentFullProfileTool::class)->execute($user, ['identifier' => $lastname]);

        $this->assertNoNonScalarLeaves($result);
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
