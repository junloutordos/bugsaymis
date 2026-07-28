<?php

namespace Tests\Feature\Atlas;

use App\Models\Atlas\WorkspaceSyncProposal;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\GoogleWorkspaceDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class WorkspaceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_proposes_a_high_confidence_match_for_a_student_missing_an_email(): void
    {
        // students is a legacy MyISAM table — RefreshDatabase can't roll it back between
        // tests, so every test here uses a name unique to itself to avoid cross-test matches.
        $lastname = 'Highconf'.uniqid();

        $this->fakeDirectory([
            ['primaryEmail' => "highconf-{$lastname}@crc.pshs.edu.ph", 'fullName' => "Aizel Shine {$lastname}", 'orgUnitPath' => '/Batch 2029 Students'],
        ]);

        $studentId = $this->student('Aizel Shine', $lastname, '2029', null);

        $this->actingAs($this->userWithPermissions(['atlas.workspace-sync.view', 'atlas.workspace-sync.manage']))
            ->post(route('atlas.workspace-sync.scan'))
            ->assertRedirect();

        $this->assertDatabaseHas('workspace_sync_proposals', [
            'subject_type' => 'student',
            'subject_id' => $studentId,
            'proposed_email' => "highconf-{$lastname}@crc.pshs.edu.ph",
            'confidence' => 'high',
            'status' => 'pending',
        ]);
    }

    public function test_scan_skips_a_student_whose_email_is_already_correct(): void
    {
        // students is a legacy MyISAM table — RefreshDatabase can't roll it back between
        // tests, so use a name unique to this test to avoid matching leftover rows.
        $lastname = 'Correctlinked'.uniqid();

        $this->fakeDirectory([
            ['primaryEmail' => 'already-correct@crc.pshs.edu.ph', 'fullName' => "Aizel Shine {$lastname}", 'orgUnitPath' => '/Batch 2029 Students'],
        ]);

        $this->student('Aizel Shine', $lastname, '2029', 'already-correct@crc.pshs.edu.ph');

        $this->actingAs($this->userWithPermissions(['atlas.workspace-sync.view', 'atlas.workspace-sync.manage']))
            ->post(route('atlas.workspace-sync.scan'));

        $this->assertDatabaseMissing('workspace_sync_proposals', [
            'subject_name' => "Aizel Shine {$lastname}",
        ]);
    }

    public function test_scan_downgrades_confidence_when_workspace_org_unit_does_not_match_the_students_batch(): void
    {
        $lastname = 'Wrongou'.uniqid();

        $this->fakeDirectory([
            ['primaryEmail' => "wrongou-{$lastname}@crc.pshs.edu.ph", 'fullName' => "Aizel Shine {$lastname}", 'orgUnitPath' => '/Batch 2028 Students'],
        ]);

        $this->student('Aizel Shine', $lastname, '2029', null);

        $this->actingAs($this->userWithPermissions(['atlas.workspace-sync.view', 'atlas.workspace-sync.manage']))
            ->post(route('atlas.workspace-sync.scan'));

        $this->assertDatabaseHas('workspace_sync_proposals', [
            'subject_name' => "Aizel Shine {$lastname}",
            'confidence' => 'medium',
        ]);
    }

    public function test_approving_a_proposal_writes_the_student_email_and_audit_logs_it(): void
    {
        $lastname = 'Approve'.uniqid();
        $studentId = $this->student('Aizel Shine', $lastname, '2029', null);

        $proposal = WorkspaceSyncProposal::create([
            'subject_type' => 'student',
            'subject_id' => $studentId,
            'subject_name' => "Aizel Shine {$lastname}",
            'current_email' => null,
            'proposed_email' => "approve-{$lastname}@crc.pshs.edu.ph",
            'org_unit_path' => '/Batch 2029 Students',
            'confidence' => 'high',
            'status' => 'pending',
        ]);

        $this->actingAs($this->userWithPermissions(['atlas.workspace-sync.view', 'atlas.workspace-sync.manage']))
            ->post(route('atlas.workspace-sync.approve', $proposal->id))
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'student_email' => "approve-{$lastname}@crc.pshs.edu.ph",
        ]);
        $this->assertDatabaseHas('workspace_sync_proposals', [
            'id' => $proposal->id,
            'status' => 'approved',
        ]);
        $this->assertSame(1, AuditLog::where('auditable_type', 'App\\Models\\Student')
            ->where('auditable_id', $studentId)->count());
    }

    public function test_rejecting_a_proposal_leaves_the_student_record_untouched(): void
    {
        $lastname = 'Reject'.uniqid();
        $studentId = $this->student('Aizel Shine', $lastname, '2029', null);

        $proposal = WorkspaceSyncProposal::create([
            'subject_type' => 'student',
            'subject_id' => $studentId,
            'subject_name' => "Aizel Shine {$lastname}",
            'current_email' => null,
            'proposed_email' => 'wrong-guess@crc.pshs.edu.ph',
            'org_unit_path' => '/Batch 2029 Students',
            'confidence' => 'medium',
            'status' => 'pending',
        ]);

        $this->actingAs($this->userWithPermissions(['atlas.workspace-sync.view', 'atlas.workspace-sync.manage']))
            ->post(route('atlas.workspace-sync.reject', $proposal->id))
            ->assertRedirect();

        $this->assertDatabaseHas('students', ['id' => $studentId, 'student_email' => null]);
        $this->assertDatabaseHas('workspace_sync_proposals', ['id' => $proposal->id, 'status' => 'rejected']);
    }

    public function test_scan_proposes_a_match_for_an_employee_missing_an_email(): void
    {
        $this->fakeDirectory([
            ['primaryEmail' => 'mcabamonga@crc.pshs.edu.ph', 'fullName' => 'Maria Cabamonga', 'orgUnitPath' => '/Faculty'],
        ]);

        $employee = User::factory()->create([
            'name' => 'Maria Cabamonga',
            'email' => 'wrong-address-on-file@example.com',
            'account_type' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($this->userWithPermissions(['atlas.workspace-sync.view', 'atlas.workspace-sync.manage']))
            ->post(route('atlas.workspace-sync.scan'));

        $this->assertDatabaseHas('workspace_sync_proposals', [
            'subject_type' => 'employee',
            'subject_id' => $employee->id,
            'proposed_email' => 'mcabamonga@crc.pshs.edu.ph',
        ]);
    }

    public function test_route_requires_the_manage_permission_to_run_a_scan(): void
    {
        $this->fakeDirectory([]);

        $this->actingAs($this->userWithPermission('atlas.workspace-sync.view'))
            ->post(route('atlas.workspace-sync.scan'))
            ->assertForbidden();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function fakeDirectory(array $users): void
    {
        $mock = Mockery::mock(GoogleWorkspaceDirectoryService::class);
        $mock->shouldReceive('listUsers')->andReturn($users);
        $this->app->instance(GoogleWorkspaceDirectoryService::class, $mock);
    }

    private function student(string $firstname, string $lastname, string $batch, ?string $studentEmail): int
    {
        return DB::table('students')->insertGetId([
            'firstname' => $firstname,
            'lastname' => $lastname,
            'batch' => $batch,
            'student_email' => $studentEmail,
            'status' => 'Enrolled',
        ]);
    }

    private function userWithPermission(string $permissionName): User
    {
        return $this->userWithPermissions([$permissionName]);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Workspace Sync Test '.uniqid()]);

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['module' => 'Atlas', 'description' => $permissionName],
            );
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
