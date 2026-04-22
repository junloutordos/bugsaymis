<?php

namespace Tests\Feature\SALN;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SALN\SalnRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the SALN committee review workflow:
 *   review queue → open → approve / return
 */
class SalnReviewTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    protected function userWithPermissions(array $names): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);

        foreach ($names as $name) {
            $perm = Permission::firstOrCreate(['name' => $name], [
                'module'      => 'SALN',
                'description' => $name,
            ]);
            $role->permissions()->attach($perm->id);
        }

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function committeeUser(): User
    {
        return $this->userWithPermissions(['saln.review', 'saln.approve']);
    }

    protected function hrUser(): User
    {
        return $this->userWithPermissions(['saln.view_all', 'saln.file', 'saln.review', 'saln.approve']);
    }

    protected function employeeUser(): User
    {
        return $this->userWithPermissions(['saln.create', 'saln.submit']);
    }

    protected function makeSaln(User $user, array $overrides = []): SalnRecord
    {
        return SalnRecord::create(array_merge([
            'user_id'      => $user->id,
            'year'         => 2024,
            'as_of_date'   => '2024-12-31',
            'status'       => 'submitted',
            'submitted_at' => now(),
        ], $overrides));
    }

    // ─── Queue Index ───────────────────────────────────────────────────────────

    public function test_committee_member_can_view_review_queue(): void
    {
        $committee = $this->committeeUser();
        $employee  = $this->employeeUser();
        $this->makeSaln($employee);

        $this->actingAs($committee)
            ->get(route('saln.review.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SALN/Review/Index')
                ->has('records.data', 1)
            );
    }

    public function test_employee_cannot_access_review_queue(): void
    {
        $employee = $this->employeeUser();

        $this->actingAs($employee)
            ->get(route('saln.review.index'))
            ->assertForbidden();
    }

    // ─── Open for review (auto-transition) ────────────────────────────────────

    public function test_opening_submitted_saln_transitions_to_under_review(): void
    {
        $committee = $this->committeeUser();
        $employee  = $this->employeeUser();
        $saln      = $this->makeSaln($employee, ['status' => 'submitted']);

        $this->actingAs($committee)
            ->get(route('saln.review.show', $saln))
            ->assertOk();

        $this->assertDatabaseHas('saln_records', [
            'id'     => $saln->id,
            'status' => 'under_review',
        ]);
    }

    public function test_opening_under_review_saln_does_not_create_duplicate_log(): void
    {
        $committee = $this->committeeUser();
        $employee  = $this->employeeUser();
        $saln      = $this->makeSaln($employee, ['status' => 'under_review']);

        $countBefore = \DB::table('saln_reviews')->where('saln_record_id', $saln->id)->count();

        $this->actingAs($committee)->get(route('saln.review.show', $saln));

        $countAfter = \DB::table('saln_reviews')->where('saln_record_id', $saln->id)->count();

        $this->assertEquals($countBefore, $countAfter);
    }

    // ─── Approve ───────────────────────────────────────────────────────────────

    public function test_committee_can_approve_under_review_saln(): void
    {
        $committee = $this->committeeUser();
        $employee  = $this->employeeUser();
        $saln      = $this->makeSaln($employee, ['status' => 'under_review']);

        $this->actingAs($committee)
            ->post(route('saln.review.approve', $saln), ['remarks' => 'Looks good.'])
            ->assertRedirect();

        $this->assertDatabaseHas('saln_records', [
            'id'     => $saln->id,
            'status' => 'approved',
        ]);
    }

    public function test_approve_logs_action_in_audit_trail(): void
    {
        $committee = $this->committeeUser();
        $employee  = $this->employeeUser();
        $saln      = $this->makeSaln($employee, ['status' => 'under_review']);

        $this->actingAs($committee)
            ->post(route('saln.review.approve', $saln), ['remarks' => 'All clear']);

        $this->assertDatabaseHas('saln_reviews', [
            'saln_record_id' => $saln->id,
            'actor_id'       => $committee->id,
            'action'         => 'approved',
            'status_from'    => 'under_review',
            'status_to'      => 'approved',
            'remarks'        => 'All clear',
        ]);
    }

    public function test_user_without_approve_permission_cannot_approve(): void
    {
        // Review-only user (no saln.approve)
        $reviewOnly = $this->userWithPermissions(['saln.review']);
        $employee   = $this->employeeUser();
        $saln       = $this->makeSaln($employee, ['status' => 'under_review']);

        $this->actingAs($reviewOnly)
            ->post(route('saln.review.approve', $saln), ['remarks' => ''])
            ->assertForbidden();
    }

    // ─── Return ────────────────────────────────────────────────────────────────

    public function test_committee_can_return_saln_with_remarks(): void
    {
        $committee = $this->committeeUser();
        $employee  = $this->employeeUser();
        $saln      = $this->makeSaln($employee, ['status' => 'under_review']);

        $this->actingAs($committee)
            ->post(route('saln.review.return', $saln), [
                'remarks' => 'Please correct the fair market values.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('saln_records', [
            'id'     => $saln->id,
            'status' => 'returned',
        ]);
    }

    public function test_return_requires_remarks(): void
    {
        $committee = $this->committeeUser();
        $employee  = $this->employeeUser();
        $saln      = $this->makeSaln($employee, ['status' => 'under_review']);

        $this->actingAs($committee)
            ->post(route('saln.review.return', $saln), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');
    }

    public function test_returned_saln_becomes_editable_again(): void
    {
        $employee = $this->employeeUser();
        $saln     = $this->makeSaln($employee, ['status' => 'returned']);

        $this->assertTrue($saln->isEditable());
    }

    // ─── HR filing ─────────────────────────────────────────────────────────────

    public function test_hr_can_file_approved_saln(): void
    {
        $hr       = $this->hrUser();
        $employee = $this->employeeUser();
        $saln     = $this->makeSaln($employee, ['status' => 'approved', 'approved_at' => now()]);

        $this->actingAs($hr)
            ->post(route('saln.hr.file', $saln), ['remarks' => 'Received.'])
            ->assertRedirect();

        $this->assertDatabaseHas('saln_records', [
            'id'     => $saln->id,
            'status' => 'filed',
        ]);
    }

    public function test_hr_cannot_file_draft_saln(): void
    {
        $hr       = $this->hrUser();
        $employee = $this->employeeUser();
        $saln     = $this->makeSaln($employee, ['status' => 'draft']);

        $this->actingAs($hr)
            ->post(route('saln.hr.file', $saln), ['remarks' => ''])
            ->assertRedirect(); // back with error, not 403

        $this->assertDatabaseHas('saln_records', ['id' => $saln->id, 'status' => 'draft']);
    }

    public function test_committee_without_file_permission_cannot_file(): void
    {
        $committee = $this->committeeUser(); // has review+approve but NOT file
        $employee  = $this->employeeUser();
        $saln      = $this->makeSaln($employee, ['status' => 'approved']);

        $this->actingAs($committee)
            ->post(route('saln.hr.file', $saln), ['remarks' => ''])
            ->assertForbidden();
    }
}
