<?php

namespace Tests\Feature\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Registrar\EnrollmentApplication;
use App\Models\Role;
use App\Models\User;
use App\Services\GoogleWorkspaceDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class EnrollmentApplicationProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_an_application_auto_provisions_a_workspace_account(): void
    {
        Mail::fake();

        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldReceive('emailExists')->andReturn(false);
        $mock->shouldReceive('createUser')
            ->withArgs(function ($email, $given, $family, $password, $orgUnit) {
                return str_starts_with($email, 'j') && str_ends_with($email, '@crc.pshs.edu.ph')
                    && str_starts_with($orgUnit, '/Batch ');
            })
            ->andReturnUsing(fn ($email) => $email);
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $lastname = 'Provtest'.uniqid();
        $application = $this->application($lastname);

        $response = $this->actingAs($this->userWithPermission('students.enrollment.manage'))
            ->post(route('registrar.enrollment-applications.approve', $application->id), [
                'pisays_id' => '13-2026-'.random_int(100, 999),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', function ($message) {
            return str_contains($message, 'Google account created') && str_contains($message, 'temporary password');
        });

        $studentId = DB::table('enrollment_applications')->where('id', $application->id)->value('student_id');
        $this->assertNotNull($studentId);

        $studentEmail = DB::table('students')->where('id', $studentId)->value('student_email');
        $this->assertStringEndsWith('@crc.pshs.edu.ph', $studentEmail);
    }

    public function test_enrollment_still_succeeds_when_workspace_provisioning_fails(): void
    {
        Mail::fake();

        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldReceive('emailExists')->andReturn(false);
        $mock->shouldReceive('createUser')->andThrow(new \RuntimeException('Workspace API down'));
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);

        $lastname = 'Provfail'.uniqid();
        $application = $this->application($lastname);

        $response = $this->actingAs($this->userWithPermission('students.enrollment.manage'))
            ->post(route('registrar.enrollment-applications.approve', $application->id), [
                'pisays_id' => '13-2026-'.random_int(100, 999),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', function ($message) {
            return str_contains($message, 'approved') && ! str_contains($message, 'temporary password');
        });

        $studentId = DB::table('enrollment_applications')->where('id', $application->id)->value('student_id');
        $this->assertNotNull($studentId, 'Student record should still be created even if Workspace provisioning fails.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function application(string $lastname): EnrollmentApplication
    {
        $sy = SchoolYear::factory()->create(['is_current' => true, 'name' => '2026-2027']);

        return EnrollmentApplication::create([
            'reference_no' => 'ENR-'.uniqid(),
            'school_year_id' => $sy->id,
            'grade_level' => 7,
            'lastname' => $lastname,
            'firstname' => 'Juan',
            'status' => 'pending',
        ]);
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Registrar', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'Enrollment Provisioning Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
