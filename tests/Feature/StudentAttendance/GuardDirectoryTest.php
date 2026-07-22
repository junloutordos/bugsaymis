<?php

namespace Tests\Feature\StudentAttendance;

use App\Http\Middleware\EnsureStudentAttendanceDevice;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Models\StudentAttendance\StudentAttendanceKioskOperator;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuardDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_directory_requires_a_paired_device_and_active_guard_session(): void
    {
        $this->getJson(route('student-attendance.directory.search', ['type' => 'student', 'q' => 'Juan']))
            ->assertForbidden();

        $guard = $this->userWithRole('Security Guard');
        [, $token] = $this->registeredDevice($guard);

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->getJson(route('student-attendance.directory.search', ['type' => 'student', 'q' => 'Juan']))
            ->assertForbidden();
    }

    public function test_guard_can_find_current_student_and_view_only_approved_information(): void
    {
        Carbon::setTestNow('2026-07-22 08:30:00');
        [$guard, $device, $token] = $this->unlockedGuard();
        $records = $this->academicRecords();

        $search = $this->guardGet($token, route('student-attendance.directory.search', [
            'type' => 'student',
            'q' => 'Dela Cruz',
        ]));

        $search->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $records['student_id'])
            ->assertJsonMissingPath('data.0.birthday')
            ->assertJsonMissingPath('data.0.address');

        $detail = $this->guardGet($token, route('student-attendance.directory.students.show', $records['student_id']));
        $detail->assertOk()
            ->assertJsonPath('data.adviser.name', $records['teacher']->name)
            ->assertJsonPath('data.current_or_next.status', 'current')
            ->assertJsonPath('data.current_or_next.room', 'Science Hall 101')
            ->assertJsonPath('data.parents.0.relationship', 'mother')
            ->assertJsonPath('data.parents.0.mobile_phone', '•••••••4567')
            ->assertJsonMissingPath('data.birthday')
            ->assertJsonMissingPath('data.home_address')
            ->assertJsonMissingPath('data.bloodtype')
            ->assertJsonMissingPath('data.parents.0.fcm_device_token');

        $audit = AuditLog::where('action', 'guard.student.view')->latest()->firstOrFail();
        $this->assertSame($guard->id, $audit->user_id);
        $this->assertSame(Student::class, $audit->auditable_type);
        $this->assertSame($records['student_id'], (int) $audit->auditable_id);
        $this->assertSame($device->id, $audit->new_values['kiosk_device_id']);
    }

    public function test_parent_contact_reveal_is_explicit_and_audited(): void
    {
        [$guard, $device, $token] = $this->unlockedGuard();
        $records = $this->academicRecords();

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.directory.students.contacts', $records['student_id']))
            ->assertOk()
            ->assertJsonPath('data.0.mobile_phone', '09171234567')
            ->assertJsonPath('data.0.email', 'maria@example.test');

        $audit = AuditLog::where('action', 'guard.student.contacts_revealed')->latest()->firstOrFail();
        $this->assertSame($guard->id, $audit->user_id);
        $this->assertSame($device->id, $audit->new_values['kiosk_device_id']);
        $this->assertArrayNotHasKey('mobile_phone', $audit->new_values);
        $this->assertArrayNotHasKey('email', $audit->new_values);
    }

    public function test_guard_can_view_employee_office_and_teaching_schedule_without_hr_data(): void
    {
        Carbon::setTestNow('2026-07-22 08:30:00');
        [, , $token] = $this->unlockedGuard();
        $records = $this->academicRecords();

        $this->guardGet($token, route('student-attendance.directory.search', [
            'type' => 'employee',
            'q' => $records['teacher']->name,
        ]))->assertOk()->assertJsonPath('data.0.id', $records['teacher']->id);

        $this->guardGet($token, route('student-attendance.directory.employees.show', $records['teacher']->id))
            ->assertOk()
            ->assertJsonPath('data.position', 'Special Science Teacher')
            ->assertJsonPath('data.office', 'Curriculum Office')
            ->assertJsonPath('data.current_or_next.status', 'current')
            ->assertJsonPath('data.current_or_next.section', 'Diamond')
            ->assertJsonMissingPath('data.salary_grade')
            ->assertJsonMissingPath('data.leave_applications')
            ->assertJsonMissingPath('data.dtr_records')
            ->assertJsonMissingPath('data.personal_phone');
    }

    public function test_search_and_scan_audits_use_the_pin_operator_and_do_not_store_raw_queries(): void
    {
        Carbon::setTestNow('2026-07-22 08:30:00');
        [$guard, $device, $token] = $this->unlockedGuard();
        $records = $this->academicRecords();

        $this->guardGet($token, route('student-attendance.directory.search', [
            'type' => 'student',
            'q' => 'Dela Cruz',
        ]))->assertOk();

        $searchAudit = AuditLog::where('action', 'guard.directory.search')->latest()->firstOrFail();
        $this->assertSame($guard->id, $searchAudit->user_id);
        $this->assertSame($device->id, $searchAudit->new_values['kiosk_device_id']);
        $this->assertStringNotContainsString('Dela Cruz', json_encode($searchAudit->new_values));

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.scan'), [
                'barcode' => $records['barcode'],
                'scan_uuid' => (string) Str::uuid(),
                'device_scan_time' => now()->toIso8601String(),
            ])->assertOk();

        $scanAudit = AuditLog::where('action', 'guard.attendance.scan')->latest()->firstOrFail();
        $this->assertSame($guard->id, $scanAudit->user_id);
        $this->assertSame($records['student_id'], (int) $scanAudit->auditable_id);
        $this->assertSame('ok', $scanAudit->new_values['outcome']);
    }

    public function test_administrator_can_filter_audit_report_by_guard_and_kiosk_device(): void
    {
        [$guard, $device] = $this->unlockedGuard();
        $administrator = $this->userWithRole('Administrator');

        $this->actingAs($administrator)
            ->get(route('reports.audit_logs', [
                'guard_activity' => 1,
                'guard_id' => $guard->id,
                'kiosk_device_id' => $device->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/AuditLogs/Index')
                ->has('auditLogs.data', 1)
                ->where('auditLogs.data.0.action', 'guard.kiosk.unlocked')
                ->where('auditLogs.data.0.user_id', $guard->id));
    }

    private function academicRecords(): array
    {
        $schoolYearId = DB::table('school_years')->insertGetId([
            'name' => '2026-2027',
            'start_date' => '2026-06-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $termId = DB::table('academic_terms')->insertGetId([
            'school_year_id' => $schoolYearId,
            'name' => '1st Semester',
            'term_type' => '1st_semester',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $officeId = DB::table('offices')->insertGetId(['name' => 'Curriculum Office']);
        $teacher = User::factory()->create([
            'name' => 'Dr. Ana Reyes',
            'account_type' => 'employee',
            'status' => 'active',
            'position' => 'Special Science Teacher',
            'office_id' => $officeId,
            'employee_no' => 'EMP-1001',
        ]);

        $sectionId = DB::table('sections')->insertGetId([
            'levelid' => 7,
            'sectionname' => 'Diamond',
            'adviser' => $teacher->id,
            'syid' => $schoolYearId,
            'school_year_id' => $schoolYearId,
            'is_active' => true,
        ]);
        $barcode = 'T'.Str::upper(Str::random(10));
        $studentId = DB::table('students')->insertGetId([
            'lastname' => 'Dela Cruz',
            'firstname' => 'Juan',
            'middlename' => 'Santos',
            'nickname' => 'JD',
            'birthday' => '2012-01-02',
            'bloodtype' => 'O+',
            'contact_address1' => 'Sensitive Home Address',
            'pisaysystemID' => $barcode,
        ]);
        DB::table('student_enrollments')->insert([
            'student_id' => $studentId,
            'school_year_id' => $schoolYearId,
            'section_id' => $sectionId,
            'grade_level' => 7,
            'enrollment_type' => 'returning',
            'status' => 'enrolled',
            'enrollment_date' => '2026-06-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parentId = DB::table('parent_contacts')->insertGetId([
            'name' => 'Maria Dela Cruz',
            'email' => 'maria@example.test',
            'mobile_phone' => '09171234567',
            'fcm_device_token' => 'must-never-be-returned',
            'notify_email' => true,
            'notify_push' => true,
            'notify_sms' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('student_parent_contact')->insert([
            'student_id' => $studentId,
            'parent_contact_id' => $parentId,
            'relationship' => 'mother',
        ]);

        $subjectId = DB::table('subjects')->insertGetId([
            'school_year_id' => $schoolYearId,
            'code' => 'SCI7-1',
            'name' => 'Integrated Science',
            'grade_level' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classrooms')->insertGetId([
            'school_year_id' => $schoolYearId,
            'name' => 'Science Hall 101',
            'code' => 'SH-101',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('class_schedules')->insert([
            'user_id' => $teacher->id,
            'subject_id' => $subjectId,
            'section_id' => $sectionId,
            'classroom_id' => $classroomId,
            'school_year_id' => $schoolYearId,
            'academic_term_id' => $termId,
            'entry_type' => 'class',
            'day_of_week' => 'Wednesday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['student_id' => $studentId, 'barcode' => $barcode, 'teacher' => $teacher];
    }

    private function unlockedGuard(): array
    {
        $guard = $this->userWithRole('Security Guard');
        [$device, $token] = $this->registeredDevice($guard);
        StudentAttendanceKioskOperator::create([
            'user_id' => $guard->id,
            'pin_hash' => Hash::make('123456'),
            'is_active' => true,
            'set_by' => $guard->id,
            'pin_changed_at' => now(),
        ]);

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.kiosk.unlock'), [
                'operator_id' => $guard->id,
                'pin' => '123456',
            ])->assertOk();

        return [$guard, $device, $token];
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->load('roles');
    }

    private function registeredDevice(User $pairedBy): array
    {
        $token = Str::random(80);
        $device = StudentAttendanceDevice::create([
            'name' => 'Main Gate iPad',
            'gate_location' => 'main_gate',
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
            'paired_by' => $pairedBy->id,
        ]);

        return [$device, $token];
    }

    private function guardGet(string $token, string $url)
    {
        return $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->getJson($url);
    }
}
