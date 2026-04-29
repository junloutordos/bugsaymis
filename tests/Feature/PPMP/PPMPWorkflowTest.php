<?php

namespace Tests\Feature\PPMP;

use App\Models\Division;
use App\Models\Permission;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpItem;
use App\Models\PPMP\PpmpStatusHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the PPMP lifecycle:
 *   create draft → add items → validate → submit → approve/return → consolidate
 */
class PPMPWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    protected function userWithPermissions(array $permissionNames, ?Division $division = null): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);

        foreach ($permissionNames as $name) {
            $perm = Permission::firstOrCreate(['name' => $name], [
                'module'      => 'ppmp',
                'description' => $name,
            ]);
            $role->permissions()->attach($perm->id);
        }

        $division = $division ?? Division::firstOrCreate(
            ['division_name' => 'Test Division'],
            ['acronym' => 'TD', 'status' => 'active']
        );

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'division_id'       => $division->id,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function endUser(?Division $division = null): User
    {
        return $this->userWithPermissions(['ppmp.create', 'ppmp.submit', 'ppmp.export'], $division);
    }

    protected function reviewer(): User
    {
        return $this->userWithPermissions(['ppmp.review', 'ppmp.approve', 'ppmp.view_all', 'ppmp.export']);
    }

    protected function procurementOfficer(): User
    {
        return $this->userWithPermissions([
            'ppmp.review', 'ppmp.approve', 'ppmp.consolidate', 'ppmp.view_all', 'ppmp.export',
        ]);
    }

    protected function makePpmp(User $user, array $overrides = []): Ppmp
    {
        return Ppmp::create(array_merge([
            'ppmp_number' => 'PPMP-2026-TD-001',
            'title'       => 'Office Supplies',
            'fiscal_year' => 2026,
            'division_id' => $user->division_id,
            'prepared_by' => $user->id,
            'status'      => Ppmp::STATUS_DRAFT,
        ], $overrides));
    }

    protected function addItem(Ppmp $ppmp, array $overrides = []): PpmpItem
    {
        return PpmpItem::create(array_merge([
            'ppmp_id'            => $ppmp->id,
            'description'        => 'Bond Paper A4',
            'unit'               => 'ream',
            'category'           => 'goods',
            'unit_cost'          => 250.00,
            'jan'                => 10,
            'feb'                => 10,
            'mar'                => 10,
            'apr'                => 0,
            'may'                => 0,
            'jun'                => 0,
            'jul'                => 0,
            'aug'                => 0,
            'sep'                => 0,
            'oct'                => 0,
            'nov'                => 0,
            'dec'                => 0,
            'procurement_method' => 'shopping',
            'is_ps_dbm'          => true,
            'sort_order'         => 0,
        ], $overrides));
    }

    // ─── Access Control ───────────────────────────────────────────────────────

    public function test_guest_cannot_access_ppmp_index(): void
    {
        $this->get(route('ppmp.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_ppmp(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('ppmp.index'))
            ->assertForbidden();
    }

    public function test_end_user_can_view_ppmp_index(): void
    {
        $user = $this->endUser();
        $this->makePpmp($user);

        $this->actingAs($user)
            ->get(route('ppmp.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PPMP/Index')
                ->has('ppmps', 1)
            );
    }

    public function test_end_user_sees_only_own_division_ppmps(): void
    {
        $div1 = Division::create(['division_name' => 'Division A', 'acronym' => 'DA', 'status' => 'active']);
        $div2 = Division::create(['division_name' => 'Division B', 'acronym' => 'DB', 'status' => 'active']);

        $user1 = $this->endUser($div1);
        $user2 = $this->endUser($div2);

        $this->makePpmp($user1, ['ppmp_number' => 'PPMP-2026-DA-001']);
        $this->makePpmp($user2, ['ppmp_number' => 'PPMP-2026-DB-001']);

        $this->actingAs($user1)
            ->get(route('ppmp.index'))
            ->assertInertia(fn ($page) => $page->has('ppmps', 1));
    }

    public function test_reviewer_sees_all_ppmps(): void
    {
        $div1 = Division::create(['division_name' => 'Division A', 'acronym' => 'DA', 'status' => 'active']);
        $div2 = Division::create(['division_name' => 'Division B', 'acronym' => 'DB', 'status' => 'active']);

        $user1 = $this->endUser($div1);
        $user2 = $this->endUser($div2);

        $this->makePpmp($user1, ['ppmp_number' => 'PPMP-2026-DA-001']);
        $this->makePpmp($user2, ['ppmp_number' => 'PPMP-2026-DB-001']);

        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)
            ->get(route('ppmp.index'))
            ->assertInertia(fn ($page) => $page->has('ppmps', 2));
    }

    // ─── Create PPMP ──────────────────────────────────────────────────────────

    public function test_end_user_can_create_ppmp(): void
    {
        $user = $this->endUser();

        $this->actingAs($user)
            ->post(route('ppmp.store'), [
                'fiscal_year' => 2026,
                'title'       => 'Office Supplies',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ppmp', [
            'division_id' => $user->division_id,
            'prepared_by' => $user->id,
            'status'      => 'draft',
            'fiscal_year' => 2026,
        ]);

        // Status history logged
        $ppmp = Ppmp::where('prepared_by', $user->id)->first();
        $this->assertDatabaseHas('ppmp_status_history', [
            'ppmp_id'   => $ppmp->id,
            'to_status' => 'draft',
            'acted_by'  => $user->id,
        ]);
    }

    public function test_ppmp_number_is_auto_generated(): void
    {
        $user = $this->endUser();

        $this->actingAs($user)
            ->post(route('ppmp.store'), [
                'fiscal_year' => 2026,
                'title'       => 'Test',
            ]);

        $ppmp = Ppmp::where('prepared_by', $user->id)->first();
        $this->assertMatchesRegularExpression('/^PPMP-2026-TD-\d{3}$/', $ppmp->ppmp_number);
    }

    public function test_cannot_create_duplicate_active_ppmp(): void
    {
        $user = $this->endUser();
        $this->makePpmp($user, ['fiscal_year' => 2026, 'status' => 'draft']);

        $this->actingAs($user)
            ->post(route('ppmp.store'), [
                'fiscal_year' => 2026,
                'title'       => 'Duplicate',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_can_create_ppmp_for_different_year(): void
    {
        $user = $this->endUser();
        $this->makePpmp($user, ['fiscal_year' => 2026, 'ppmp_number' => 'PPMP-2026-TD-001']);

        $this->actingAs($user)
            ->post(route('ppmp.store'), [
                'fiscal_year' => 2027,
                'title'       => 'Next Year',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ppmp', ['fiscal_year' => 2027, 'prepared_by' => $user->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->endUser();

        $this->actingAs($user)
            ->post(route('ppmp.store'), [])
            ->assertSessionHasErrors(['fiscal_year', 'title']);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_end_user_can_view_own_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->get(route('ppmp.show', $ppmp))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PPMP/Show'));
    }

    public function test_end_user_cannot_view_other_division_ppmp(): void
    {
        $div1 = Division::create(['division_name' => 'Div A', 'acronym' => 'DA', 'status' => 'active']);
        $div2 = Division::create(['division_name' => 'Div B', 'acronym' => 'DB', 'status' => 'active']);

        $owner = $this->endUser($div1);
        $other = $this->endUser($div2);
        $ppmp = $this->makePpmp($owner, ['ppmp_number' => 'PPMP-2026-DA-001']);

        $this->actingAs($other)
            ->get(route('ppmp.show', $ppmp))
            ->assertForbidden();
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_end_user_can_delete_draft_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->delete(route('ppmp.destroy', $ppmp))
            ->assertRedirect(route('ppmp.index'));

        $this->assertDatabaseMissing('ppmp', ['id' => $ppmp->id]);
    }

    public function test_end_user_cannot_delete_submitted_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_SUBMITTED]);

        $this->actingAs($user)
            ->delete(route('ppmp.destroy', $ppmp))
            ->assertForbidden();
    }

    // ─── Submit ───────────────────────────────────────────────────────────────

    public function test_end_user_can_submit_ppmp_with_valid_items(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);
        $this->addItem($ppmp);

        $this->actingAs($user)
            ->post(route('ppmp.submit', $ppmp))
            ->assertRedirect()
            ->assertSessionHas('success');

        $ppmp->refresh();
        $this->assertEquals(Ppmp::STATUS_SUBMITTED, $ppmp->status);
        $this->assertNotNull($ppmp->submitted_at);
    }

    public function test_cannot_submit_ppmp_without_items(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->post(route('ppmp.submit', $ppmp))
            ->assertRedirect()
            ->assertSessionHas('error');

        $ppmp->refresh();
        $this->assertEquals(Ppmp::STATUS_DRAFT, $ppmp->status);
    }

    public function test_submit_logs_status_history(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);
        $this->addItem($ppmp);

        $this->actingAs($user)->post(route('ppmp.submit', $ppmp));

        $this->assertDatabaseHas('ppmp_status_history', [
            'ppmp_id'     => $ppmp->id,
            'from_status' => 'draft',
            'to_status'   => 'submitted',
            'acted_by'    => $user->id,
        ]);
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function test_reviewer_can_approve_submitted_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_SUBMITTED]);
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)
            ->post(route('ppmp.approve', $ppmp))
            ->assertRedirect()
            ->assertSessionHas('success');

        $ppmp->refresh();
        $this->assertEquals(Ppmp::STATUS_APPROVED, $ppmp->status);
        $this->assertEquals($reviewer->id, $ppmp->approved_by);
        $this->assertNotNull($ppmp->approved_at);
    }

    public function test_end_user_cannot_approve_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_SUBMITTED]);

        $this->actingAs($user)
            ->post(route('ppmp.approve', $ppmp))
            ->assertForbidden();
    }

    public function test_cannot_approve_draft_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_DRAFT]);
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)
            ->post(route('ppmp.approve', $ppmp))
            ->assertForbidden();
    }

    // ─── Return ───────────────────────────────────────────────────────────────

    public function test_reviewer_can_return_ppmp_with_remarks(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_SUBMITTED]);
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)
            ->post(route('ppmp.return', $ppmp), ['remarks' => 'Fix unit costs'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $ppmp->refresh();
        $this->assertEquals(Ppmp::STATUS_RETURNED, $ppmp->status);
        $this->assertEquals('Fix unit costs', $ppmp->remarks);
    }

    public function test_return_requires_remarks(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_SUBMITTED]);
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)
            ->post(route('ppmp.return', $ppmp), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');
    }

    public function test_returned_ppmp_can_be_resubmitted(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_RETURNED]);
        $this->addItem($ppmp);

        $this->actingAs($user)
            ->post(route('ppmp.submit', $ppmp))
            ->assertRedirect()
            ->assertSessionHas('success');

        $ppmp->refresh();
        $this->assertEquals(Ppmp::STATUS_SUBMITTED, $ppmp->status);
    }

    // ─── Consolidate ──────────────────────────────────────────────────────────

    public function test_procurement_officer_can_consolidate(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_APPROVED]);
        $this->addItem($ppmp);

        $officer = $this->procurementOfficer();

        $this->actingAs($officer)
            ->post(route('ppmp.app.consolidate'), ['fiscal_year' => 2026])
            ->assertRedirect()
            ->assertSessionHas('success');

        $ppmp->refresh();
        $this->assertEquals(Ppmp::STATUS_CONSOLIDATED, $ppmp->status);
        $this->assertNotNull($ppmp->consolidated_at);
    }

    public function test_end_user_cannot_consolidate(): void
    {
        $user = $this->endUser();

        $this->actingAs($user)
            ->post(route('ppmp.app.consolidate'), ['fiscal_year' => 2026])
            ->assertForbidden();
    }
}
