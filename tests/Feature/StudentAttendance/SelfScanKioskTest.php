<?php

namespace Tests\Feature\StudentAttendance;

use App\Events\AttendanceScanEvent;
use App\Http\Middleware\EnsureStudentAttendanceDevice;
use App\Jobs\StudentAttendance\SendAttendanceSmsJob;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SelfScanKioskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake([AttendanceScanEvent::class]);
    }

    public function test_paired_self_scan_terminal_renders_student_mode_without_login_or_pin(): void
    {
        [, $token] = $this->device(StudentAttendanceDevice::MODE_STUDENT_SELF_SCAN);

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->get(route('student-attendance.kiosk'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentAttendance/Kiosk')
                ->where('device.device_mode', StudentAttendanceDevice::MODE_STUDENT_SELF_SCAN)
                ->where('operator', null)
                ->has('guards', 0));

        $this->assertGuest();
    }

    public function test_student_can_self_scan_on_paired_terminal_and_server_controls_gate(): void
    {
        [$device, $token] = $this->device(StudentAttendanceDevice::MODE_STUDENT_SELF_SCAN, 'main_gate');
        $studentId = $this->student('20260000101');

        $this->selfScan($token, '20260000101', ['gate_location' => 'side_gate'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.type', 'in')
            ->assertJsonPath('data.gate_location', 'main_gate');

        $this->assertDatabaseHas('student_attendance_logs', [
            'student_id' => $studentId,
            'gate_location' => 'main_gate',
            'recorded_by' => null,
            'kiosk_device_id' => $device->id,
            'capture_method' => 'hardware_barcode',
            'source' => 'gate_scanner',
        ]);

        $audit = AuditLog::where('action', 'student.attendance.self_scan')->latest()->firstOrFail();
        $this->assertNull($audit->user_id);
        $this->assertSame($studentId, $audit->auditable_id);
        $this->assertSame($device->id, $audit->new_values['kiosk_device_id']);
        $this->assertSame('ok', $audit->new_values['outcome']);
        Queue::assertPushed(SendAttendanceSmsJob::class, 1);
    }

    public function test_unpaired_and_revoked_terminals_cannot_self_scan(): void
    {
        $this->postJson(route('student-attendance.self-scan'), $this->payload('20260000102'))
            ->assertForbidden();

        [$device, $token] = $this->device(StudentAttendanceDevice::MODE_STUDENT_SELF_SCAN);
        $device->update(['is_active' => false]);

        $this->selfScan($token, '20260000102')->assertForbidden();
    }

    public function test_guard_and_self_scan_device_capabilities_cannot_be_crossed(): void
    {
        [, $guardToken] = $this->device(StudentAttendanceDevice::MODE_GUARD_CAMERA);
        [, $selfScanToken] = $this->device(StudentAttendanceDevice::MODE_STUDENT_SELF_SCAN);

        $this->selfScan($guardToken, '20260000103')->assertForbidden();

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $selfScanToken)
            ->postJson(route('student-attendance.scan'), $this->payload('20260000103'))
            ->assertForbidden();

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $selfScanToken)
            ->getJson(route('student-attendance.directory.search', ['type' => 'student', 'q' => 'Juan']))
            ->assertForbidden();
    }

    public function test_administrator_can_pair_a_self_scan_terminal(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('student-attendance.devices.pair'), [
                'name' => 'Main Gate Student Scanner',
                'gate_location' => 'main_gate',
                'device_mode' => StudentAttendanceDevice::MODE_STUDENT_SELF_SCAN,
            ])
            ->assertRedirect()
            ->assertCookie(EnsureStudentAttendanceDevice::COOKIE);

        $this->assertDatabaseHas('student_attendance_devices', [
            'name' => 'Main Gate Student Scanner',
            'device_mode' => StudentAttendanceDevice::MODE_STUDENT_SELF_SCAN,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.self_scan_device.paired']);
    }

    private function administrator(): User
    {
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->load('roles');
    }

    private function device(string $mode, string $gate = 'main_gate'): array
    {
        $administrator = $this->administrator();
        $token = Str::random(80);
        $device = StudentAttendanceDevice::create([
            'name' => $mode === StudentAttendanceDevice::MODE_STUDENT_SELF_SCAN
                ? 'Student Scanner'
                : 'Guard iPad',
            'gate_location' => $gate,
            'device_mode' => $mode,
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
            'paired_by' => $administrator->id,
        ]);

        return [$device, $token];
    }

    private function student(string $barcode): int
    {
        return DB::table('students')->insertGetId([
            'lastname' => 'Dela Cruz',
            'firstname' => 'Juan',
            'batch' => '2030',
            'pisaysystemID' => $barcode,
        ]);
    }

    private function payload(string $barcode, array $extra = []): array
    {
        return [
            'barcode' => $barcode,
            'scan_uuid' => (string) Str::uuid(),
            'device_scan_time' => now()->toIso8601String(),
            ...$extra,
        ];
    }

    private function selfScan(string $token, string $barcode, array $extra = [])
    {
        return $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.self-scan'), $this->payload($barcode, $extra));
    }
}
