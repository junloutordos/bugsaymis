<?php

namespace Tests\Feature\GeneralServices;

use App\Mail\WorkRequestDCApprovalMail;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WorkRequestApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $divisionChief;
    private User $requester;
    private User $gsuHead;
    private User $fadChief;
    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->divisionChief = User::factory()->create(['email' => 'dc@crc.pshs.edu.ph']);

        $this->division = Division::create([
            'division_name'     => 'Test Division',
            'acronym'           => 'TD',
            'division_chief_id' => $this->divisionChief->id,
            'status'            => 'active',
        ]);

        $this->requester = User::factory()->create(['email' => 'requester@crc.pshs.edu.ph', 'division_id' => $this->division->id]);
        $this->requester->roles()->attach($this->rolePermission('facilities.create'));
        $this->divisionChief->roles()->attach($this->rolePermission('facilities.dc-approve'));

        $gsuRole = Role::firstOrCreate(['name' => 'GSU Head']);
        $gsuRole->permissions()->attach(Permission::firstOrCreate(
            ['name' => 'facilities.create'],
            ['module' => 'Facilities', 'description' => 'facilities.create'],
        ));
        $this->gsuHead = User::factory()->create(['email' => 'gsu@crc.pshs.edu.ph', 'role_id' => (string) $gsuRole->id]);
        $this->gsuHead->roles()->attach($gsuRole);

        $this->fadChief = User::factory()->create(['email' => 'fad@crc.pshs.edu.ph', 'position' => 'FAD Chief']);
        $this->fadChief->roles()->attach($this->rolePermission('facilities.fad-approve'));
    }

    public function test_store_notifies_requesters_division_chief_first(): void
    {
        Mail::fake();

        $this->actingAs($this->requester)
            ->post(route('work-requests.store'), [
                'issue' => 'Broken aircon',
                'description' => 'Not cooling',
            ])
            ->assertRedirect(route('work-requests.index'));

        $wr = WorkRequest::first();
        $this->assertNotNull($wr);
        $this->assertSame($this->divisionChief->id, $wr->division_chief_id);
        $this->assertSame('Pending', $wr->status);

        Mail::assertQueued(WorkRequestDCApprovalMail::class, function ($mail) use ($wr) {
            return $mail->hasTo($this->divisionChief->email) && $mail->workRequest->id === $wr->id;
        });
    }

    public function test_gsu_head_cannot_approve_before_division_chief(): void
    {
        $wr = $this->makeWorkRequest();

        $this->actingAs($this->gsuHead)
            ->get(route('work-requests.gsu.approve', ['workRequest' => $wr->id, 'gsu' => $this->gsuHead->id]))
            ->assertOk();

        $this->assertSame('Pending', $wr->fresh()->status);
    }

    public function test_gsu_head_can_approve_after_division_chief_approves(): void
    {
        $wr = $this->makeWorkRequest();

        $this->actingAs($this->divisionChief)
            ->post(route('work-requests.approve.inapp', $wr->id))
            ->assertRedirect();

        $this->assertSame('Pending GSU Approval', $wr->fresh()->status);

        $this->actingAs($this->gsuHead)
            ->get(route('work-requests.gsu.approve', ['workRequest' => $wr->id, 'gsu' => $this->gsuHead->id]))
            ->assertOk();

        $this->assertSame('GSU Approved', $wr->fresh()->status);
    }

    public function test_fad_chief_cannot_approve_before_gsu_head(): void
    {
        $wr = $this->makeWorkRequest();

        $this->actingAs($this->divisionChief)
            ->post(route('work-requests.approve.inapp', $wr->id));

        $this->assertSame('Pending GSU Approval', $wr->fresh()->status);

        $fadUrl = URL::signedRoute('work-requests.fad.approve', ['workRequest' => $wr->id, 'chief' => $this->fadChief->id]);
        $this->actingAs($this->fadChief)->get($fadUrl)->assertOk();

        $this->assertSame('Pending GSU Approval', $wr->fresh()->status, 'FAD Chief must not be able to approve before GSU Head.');
    }

    public function test_full_division_chief_gsu_fad_approval_sequence(): void
    {
        $wr = $this->makeWorkRequest();

        $this->actingAs($this->divisionChief)
            ->post(route('work-requests.approve.inapp', $wr->id));
        $this->assertSame('Pending GSU Approval', $wr->fresh()->status);

        $this->actingAs($this->gsuHead)
            ->get(route('work-requests.gsu.approve', ['workRequest' => $wr->id, 'gsu' => $this->gsuHead->id]));
        $this->assertSame('GSU Approved', $wr->fresh()->status);

        $fadUrl = URL::signedRoute('work-requests.fad.approve', ['workRequest' => $wr->id, 'chief' => $this->fadChief->id]);
        $this->actingAs($this->fadChief)->get($fadUrl);
        $this->assertSame('FAD Approved', $wr->fresh()->status);
    }

    public function test_gsu_head_can_assign_staff_in_app_after_division_chief_approves(): void
    {
        $wr = $this->makeWorkRequest();
        $staff = User::factory()->create(['position' => 'Skilled Worker']);

        $this->actingAs($this->divisionChief)
            ->post(route('work-requests.approve.inapp', $wr->id));
        $this->assertSame('Pending GSU Approval', $wr->fresh()->status);

        $this->actingAs($this->gsuHead)
            ->put(route('work-requests.update', $wr->id), [
                'issue' => $wr->issue,
                'category' => 'Electrical',
                'assigned_user_id' => $staff->id,
            ])
            ->assertRedirect(route('work-requests.index'));

        $wr->refresh();
        $this->assertSame($staff->id, $wr->assigned_user_id);
        $this->assertSame('Pending FAD Approval', $wr->status);
    }

    public function test_gsu_head_cannot_assign_staff_before_division_chief_approves(): void
    {
        $wr = $this->makeWorkRequest();
        $staff = User::factory()->create(['position' => 'Skilled Worker']);

        $this->actingAs($this->gsuHead)
            ->put(route('work-requests.update', $wr->id), [
                'issue' => $wr->issue,
                'category' => 'Electrical',
                'assigned_user_id' => $staff->id,
            ])
            ->assertSessionHasErrors('assigned_user_id');

        $this->assertNull($wr->fresh()->assigned_user_id);
        $this->assertSame('Pending', $wr->fresh()->status);
    }

    public function test_gsu_head_can_assign_legacy_request_with_no_division_chief(): void
    {
        $legacyWr = WorkRequest::create([
            'issue' => 'Leaky faucet',
            'description' => 'Predates DC approval requirement',
            'status' => 'Pending',
            'requester_id' => $this->requester->id,
            'division_chief_id' => null,
            'requires_pre_repair_inspection' => false,
        ]);
        $staff = User::factory()->create(['position' => 'Skilled Worker']);

        $this->actingAs($this->gsuHead)
            ->put(route('work-requests.update', $legacyWr->id), [
                'issue' => $legacyWr->issue,
                'category' => 'Plumbing',
                'assigned_user_id' => $staff->id,
            ])
            ->assertRedirect(route('work-requests.index'));

        $legacyWr->refresh();
        $this->assertSame($staff->id, $legacyWr->assigned_user_id);
        $this->assertSame('Pending FAD Approval', $legacyWr->status);
    }

    private function makeWorkRequest(): WorkRequest
    {
        return WorkRequest::create([
            'issue' => 'Broken aircon',
            'description' => 'Not cooling',
            'status' => 'Pending',
            'requester_id' => $this->requester->id,
            'division_chief_id' => $this->divisionChief->id,
            'requires_pre_repair_inspection' => false,
        ]);
    }

    private function rolePermission(string $permissionName): Role
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Facilities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'WR Test '.uniqid()]);
        $role->permissions()->attach($permission);

        return $role;
    }
}
