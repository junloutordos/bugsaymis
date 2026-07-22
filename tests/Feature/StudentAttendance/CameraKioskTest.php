<?php

namespace Tests\Feature\StudentAttendance;

use App\Events\AttendanceScanEvent;
use App\Http\Middleware\EnsureStudentAttendanceDevice;
use App\Jobs\StudentAttendance\SendAttendanceSmsJob;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Models\StudentAttendance\StudentAttendanceKioskOperator;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CameraKioskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake([AttendanceScanEvent::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_kiosk_landing_does_not_require_google_authentication(): void
    {
        $this->get(route('student-attendance.kiosk'))->assertOk();

        $this->postJson(route('student-attendance.scan'), [
            'barcode' => '20260000001',
            'scan_uuid' => (string) Str::uuid(),
        ])->assertForbidden();
    }

    public function test_registered_ipad_still_requires_a_guard_pin_session(): void
    {
        $guard = $this->userWithRole('Security Guard');
        [, $token] = $this->registeredDevice($guard);

        $this->scan($token, '20260000001')->assertForbidden();
    }

    public function test_normal_administrator_can_scan_but_ordinary_authenticated_user_cannot(): void
    {
        $administrator = $this->userWithRole('Administrator');
        [, $token] = $this->registeredDevice($administrator);
        $this->student('20260000009');

        $this->actingAs($administrator)
            ->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.scan'), [
                'barcode' => '20260000009',
                'scan_uuid' => (string) Str::uuid(),
            ])
            ->assertOk();

        $staff = $this->userWithRole('Staff');
        $this->actingAs($staff)
            ->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.scan'), [
                'barcode' => '20260000009',
                'scan_uuid' => (string) Str::uuid(),
            ])
            ->assertForbidden();
    }

    public function test_only_administrator_can_pair_an_ipad(): void
    {
        $guard = $this->userWithRole('Security Guard');

        $this->actingAs($guard)
            ->post(route('student-attendance.devices.pair'), [
                'name' => 'Main Gate iPad',
                'gate_location' => 'main_gate',
            ])
            ->assertForbidden();

        $administrator = $this->userWithRole('Administrator');

        $this->actingAs($administrator)
            ->post(route('student-attendance.devices.pair'), [
                'name' => 'Main Gate iPad',
                'gate_location' => 'main_gate',
            ])
            ->assertRedirect()
            ->assertCookie(EnsureStudentAttendanceDevice::COOKIE);

        $this->assertDatabaseHas('student_attendance_devices', [
            'name' => 'Main Gate iPad',
            'gate_location' => 'main_gate',
            'paired_by' => $administrator->id,
            'is_active' => true,
        ]);

        $audit = AuditLog::where('action', 'admin.guard_device.paired')->latest()->firstOrFail();
        $this->assertArrayNotHasKey('token_hash', $audit->new_values);
    }

    public function test_administrator_sets_a_hashed_pin_only_for_security_guards(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $guard = $this->userWithRole('Security Guard');
        $staff = $this->userWithRole('Staff');

        $this->actingAs($administrator)
            ->put(route('student-attendance.operators.pin', $guard), [
                'pin' => '123456',
                'pin_confirmation' => '123456',
            ])
            ->assertRedirect();

        $credential = StudentAttendanceKioskOperator::where('user_id', $guard->id)->firstOrFail();
        $this->assertNotSame('123456', $credential->pin_hash);
        $this->assertTrue(Hash::check('123456', $credential->pin_hash));

        $audit = AuditLog::where('action', 'admin.guard_pin.set')->latest()->firstOrFail();
        $this->assertArrayNotHasKey('pin', $audit->new_values);
        $this->assertArrayNotHasKey('pin_hash', $audit->new_values);

        $this->actingAs($administrator)
            ->put(route('student-attendance.operators.pin', $staff), [
                'pin' => '654321',
                'pin_confirmation' => '654321',
            ])
            ->assertStatus(422);
    }

    public function test_guard_unlocks_without_a_normal_authenticated_session(): void
    {
        $guard = $this->userWithRole('Security Guard');
        [, $token] = $this->registeredDevice($guard);
        $this->setPin($guard);

        $this->unlock($guard, $token)
            ->assertOk()
            ->assertJsonPath('operator.id', $guard->id);

        $this->assertGuest();
        $this->assertDatabaseHas('student_attendance_kiosk_access_logs', [
            'user_id' => $guard->id,
            'event' => 'unlocked',
        ]);
    }

    public function test_camera_scan_uses_server_gate_and_pin_operator(): void
    {
        Carbon::setTestNow('2026-07-22 07:15:00');
        $guard = $this->userWithRole('Security Guard');
        [$device, $token] = $this->registeredDevice($guard, 'main_gate');
        $this->setPin($guard);
        $this->unlock($guard, $token)->assertOk();
        $studentId = $this->student('20260000001');

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.scan'), [
                'barcode' => '20260000001',
                'scan_uuid' => (string) Str::uuid(),
                'device_scan_time' => now()->toIso8601String(),
                'gate_location' => 'side_gate',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.type', 'in')
            ->assertJsonPath('data.gate_location', 'main_gate');

        $this->assertDatabaseHas('student_attendance_logs', [
            'student_id' => $studentId,
            'type' => 'in',
            'gate_location' => 'main_gate',
            'recorded_by' => $guard->id,
            'kiosk_device_id' => $device->id,
            'capture_method' => 'camera',
        ]);

        Queue::assertPushed(SendAttendanceSmsJob::class, 1);
    }

    public function test_scans_alternate_in_out_and_back_in_after_duplicate_window(): void
    {
        Carbon::setTestNow('2026-07-22 07:15:00');
        $guard = $this->userWithRole('Security Guard');
        [, $token] = $this->registeredDevice($guard);
        $this->setPin($guard);
        $this->unlock($guard, $token)->assertOk();
        $this->student('20260000002');

        $this->scan($token, '20260000002')->assertJsonPath('data.type', 'in');
        Carbon::setTestNow(now()->addMinutes(4));
        $this->scan($token, '20260000002')->assertJsonPath('data.type', 'out');
        Carbon::setTestNow(now()->addMinutes(4));
        $this->scan($token, '20260000002')->assertJsonPath('data.type', 'in');

        $this->assertSame(
            ['in', 'out', 'in'],
            DB::table('student_attendance_logs')->orderBy('scan_time')->pluck('type')->all(),
        );
    }

    public function test_repeated_camera_frame_is_ignored_and_does_not_notify_twice(): void
    {
        Carbon::setTestNow('2026-07-22 07:15:00');
        $guard = $this->userWithRole('Security Guard');
        [, $token] = $this->registeredDevice($guard);
        $this->setPin($guard);
        $this->unlock($guard, $token)->assertOk();
        $this->student('20260000003');

        $this->scan($token, '20260000003')->assertJsonPath('status', 'ok');
        $this->scan($token, '20260000003')->assertJsonPath('status', 'duplicate');

        $this->assertDatabaseCount('student_attendance_logs', 1);
        Queue::assertPushed(SendAttendanceSmsJob::class, 1);
    }

    public function test_failed_pin_is_limited_and_audited(): void
    {
        $guard = $this->userWithRole('Security Guard');
        [, $token] = $this->registeredDevice($guard);
        $this->setPin($guard);

        foreach (range(1, 5) as $_) {
            $this->unlock($guard, $token, '000000')->assertStatus(422);
        }

        $this->unlock($guard, $token, '000000')->assertTooManyRequests();
        $this->assertDatabaseCount('student_attendance_kiosk_access_logs', 5);
    }

    public function test_end_shift_and_session_timeout_block_further_scans(): void
    {
        Carbon::setTestNow('2026-07-22 07:15:00');
        $guard = $this->userWithRole('Security Guard');
        [, $token] = $this->registeredDevice($guard);
        $this->setPin($guard);
        $this->unlock($guard, $token)->assertOk();

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.kiosk.lock'))
            ->assertOk();

        $this->scan($token, '20260000004')->assertForbidden();

        $this->unlock($guard, $token)->assertOk();
        Carbon::setTestNow(now()->addMinutes(6));
        $this->scan($token, '20260000004')->assertForbidden();
    }

    public function test_revoked_ipad_removed_role_and_disabled_pin_block_scans(): void
    {
        $guard = $this->userWithRole('Security Guard');
        [$device, $token] = $this->registeredDevice($guard);
        $credential = $this->setPin($guard);
        $this->unlock($guard, $token)->assertOk();

        $device->update(['is_active' => false]);
        $this->scan($token, '20260000004')->assertForbidden();

        $device->update(['is_active' => true]);
        $guard->roles()->detach();
        $this->scan($token, '20260000004')->assertForbidden();

        $guard->roles()->attach(Role::where('name', 'Security Guard')->firstOrFail());
        $credential->update(['is_active' => false]);
        $this->scan($token, '20260000004')->assertForbidden();
    }

    public function test_pin_session_cannot_access_the_mis_dashboard(): void
    {
        $guard = $this->userWithRole('Security Guard');
        [, $token] = $this->registeredDevice($guard);
        $this->setPin($guard);
        $this->unlock($guard, $token)->assertOk();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_student_photo_proxy_requires_scanner_access(): void
    {
        $guard = $this->userWithRole('Security Guard');
        [, $token] = $this->registeredDevice($guard);
        $this->setPin($guard);
        $studentId = $this->student('20260000010');

        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->get(route('student-attendance.student.photo', $studentId))
            ->assertForbidden();

        $this->unlock($guard, $token)->assertOk();

        // The student has no photo, so reaching the controller correctly returns 404.
        $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->get(route('student-attendance.student.photo', $studentId))
            ->assertNotFound();
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->load('roles');
    }

    private function setPin(User $guard, string $pin = '123456'): StudentAttendanceKioskOperator
    {
        return StudentAttendanceKioskOperator::create([
            'user_id' => $guard->id,
            'pin_hash' => Hash::make($pin),
            'is_active' => true,
            'set_by' => $guard->id,
            'pin_changed_at' => now(),
        ]);
    }

    private function registeredDevice(User $pairedBy, string $gate = 'main_gate'): array
    {
        $token = Str::random(80);
        $device = StudentAttendanceDevice::create([
            'name' => 'Test Gate iPad',
            'gate_location' => $gate,
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
            'paired_by' => $pairedBy->id,
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

    private function unlock(User $guard, string $token, string $pin = '123456')
    {
        return $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.kiosk.unlock'), [
                'operator_id' => $guard->id,
                'pin' => $pin,
            ]);
    }

    private function scan(string $token, string $barcode)
    {
        return $this->withCookie(EnsureStudentAttendanceDevice::COOKIE, $token)
            ->withCredentials()
            ->postJson(route('student-attendance.scan'), [
                'barcode' => $barcode,
                'scan_uuid' => (string) Str::uuid(),
                'device_scan_time' => now()->toIso8601String(),
            ]);
    }
}
