<?php

namespace Tests\Feature\PPMP;

use App\Models\Division;
use App\Models\Permission;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for PPMP item CRUD and auto-computation.
 */
class PPMPItemTest extends TestCase
{
    use RefreshDatabase;

    protected function endUser(?Division $division = null): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);

        foreach (['ppmp.create', 'ppmp.submit', 'ppmp.export'] as $name) {
            $perm = Permission::firstOrCreate(['name' => $name], ['module' => 'ppmp', 'description' => $name]);
            $role->permissions()->attach($perm->id);
        }

        $division = $division ?? Division::firstOrCreate(
            ['division_name' => 'Test Division'],
            ['acronym' => 'TD', 'status' => 'active']
        );

        $user = User::factory()->create(['email_verified_at' => now(), 'division_id' => $division->id]);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function makePpmp(User $user, array $overrides = []): Ppmp
    {
        return Ppmp::create(array_merge([
            'ppmp_number' => 'PPMP-2026-TD-' . str_pad(Ppmp::count() + 1, 3, '0', STR_PAD_LEFT),
            'title'       => 'Test PPMP',
            'fiscal_year' => 2026,
            'division_id' => $user->division_id,
            'prepared_by' => $user->id,
            'status'      => Ppmp::STATUS_DRAFT,
        ], $overrides));
    }

    protected function validItemData(array $overrides = []): array
    {
        return array_merge([
            'description'        => 'Bond Paper A4 80gsm',
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
        ], $overrides);
    }

    // ─── Add Item ─────────────────────────────────────────────────────────────

    public function test_can_add_item_to_draft_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->post(route('ppmp.items.store', $ppmp), $this->validItemData())
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ppmp_items', [
            'ppmp_id'     => $ppmp->id,
            'description' => 'Bond Paper A4 80gsm',
            'unit'        => 'ream',
            'category'    => 'goods',
        ]);
    }

    public function test_item_totals_are_auto_computed(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->post(route('ppmp.items.store', $ppmp), $this->validItemData([
                'unit_cost' => 250.00,
                'jan'       => 10,
                'feb'       => 10,
                'mar'       => 10,
            ]));

        $item = PpmpItem::where('ppmp_id', $ppmp->id)->first();
        $this->assertEquals(30, $item->total_quantity);
        $this->assertEquals(7500.00, $item->total_cost);
    }

    public function test_item_totals_recompute_on_update(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $item = PpmpItem::create(array_merge($this->validItemData(), [
            'ppmp_id' => $ppmp->id,
            'sort_order' => 0,
        ]));

        $this->actingAs($user)
            ->put(route('ppmp.items.update', [$ppmp, $item]), $this->validItemData([
                'unit_cost' => 300.00,
                'jan'       => 20,
                'feb'       => 0,
                'mar'       => 0,
            ]));

        $item->refresh();
        $this->assertEquals(20, $item->total_quantity);
        $this->assertEquals(6000.00, $item->total_cost);
    }

    public function test_cannot_add_item_to_submitted_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_SUBMITTED]);

        $this->actingAs($user)
            ->post(route('ppmp.items.store', $ppmp), $this->validItemData())
            ->assertForbidden();
    }

    public function test_can_add_item_to_returned_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user, ['status' => Ppmp::STATUS_RETURNED]);

        $this->actingAs($user)
            ->post(route('ppmp.items.store', $ppmp), $this->validItemData())
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function test_item_requires_description(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->post(route('ppmp.items.store', $ppmp), $this->validItemData(['description' => '']))
            ->assertSessionHasErrors('description');
    }

    public function test_item_requires_valid_category(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->post(route('ppmp.items.store', $ppmp), $this->validItemData(['category' => 'invalid']))
            ->assertSessionHasErrors('category');
    }

    public function test_item_requires_positive_unit_cost(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->post(route('ppmp.items.store', $ppmp), $this->validItemData(['unit_cost' => 0]))
            ->assertSessionHasErrors('unit_cost');
    }

    public function test_item_requires_valid_procurement_method(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->post(route('ppmp.items.store', $ppmp), $this->validItemData(['procurement_method' => 'invalid']))
            ->assertSessionHasErrors('procurement_method');
    }

    public function test_month_quantities_reject_negative_values(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);

        $this->actingAs($user)
            ->post(route('ppmp.items.store', $ppmp), $this->validItemData(['jan' => -5]))
            ->assertSessionHasErrors('jan');
    }

    // ─── Delete Item ──────────────────────────────────────────────────────────

    public function test_can_delete_item_from_draft_ppmp(): void
    {
        $user = $this->endUser();
        $ppmp = $this->makePpmp($user);
        $item = PpmpItem::create(array_merge($this->validItemData(), [
            'ppmp_id' => $ppmp->id, 'sort_order' => 0,
        ]));

        $this->actingAs($user)
            ->delete(route('ppmp.items.destroy', [$ppmp, $item]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ppmp_items', ['id' => $item->id]);
    }

    public function test_cannot_delete_item_from_other_division_ppmp(): void
    {
        $div1 = Division::create(['division_name' => 'Div A', 'acronym' => 'DA', 'status' => 'active']);
        $div2 = Division::create(['division_name' => 'Div B', 'acronym' => 'DB', 'status' => 'active']);

        $owner = $this->endUser($div1);
        $other = $this->endUser($div2);

        $ppmp = $this->makePpmp($owner, ['ppmp_number' => 'PPMP-2026-DA-001']);
        $item = PpmpItem::create(array_merge($this->validItemData(), [
            'ppmp_id' => $ppmp->id, 'sort_order' => 0,
        ]));

        $this->actingAs($other)
            ->delete(route('ppmp.items.destroy', [$ppmp, $item]))
            ->assertForbidden();
    }
}
