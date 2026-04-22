<?php

namespace Tests\Feature\SALN;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SALN\SalnRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the employee-facing SALN workflow:
 *   create draft → fill sections → submit for review
 */
class SalnEmployeeTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Create a user with the given permission names attached via a throwaway role. */
    protected function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);

        foreach ($permissionNames as $name) {
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

    protected function employeeUser(): User
    {
        return $this->userWithPermissions(['saln.create', 'saln.submit']);
    }

    protected function makeSaln(User $user, array $overrides = []): SalnRecord
    {
        return SalnRecord::create(array_merge([
            'user_id'    => $user->id,
            'year'       => 2024,
            'as_of_date' => '2024-12-31',
            'status'     => 'draft',
        ], $overrides));
    }

    // ─── Index ─────────────────────────────────────────────────────────────────

    public function test_employee_can_view_own_saln_list(): void
    {
        $user = $this->employeeUser();
        $this->makeSaln($user);

        $this->actingAs($user)
            ->get(route('saln.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SALN/Index')
                ->has('records', 1)
            );
    }

    public function test_guest_cannot_access_saln_index(): void
    {
        $this->get(route('saln.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_saln_index(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('saln.index'))
            ->assertForbidden();
    }

    // ─── Create / Store ────────────────────────────────────────────────────────

    public function test_employee_can_create_draft_saln(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)
            ->post(route('saln.store'), [
                'year'       => 2024,
                'as_of_date' => '2024-12-31',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('saln_records', [
            'user_id' => $user->id,
            'year'    => 2024,
            'status'  => 'draft',
        ]);
    }

    public function test_cannot_create_duplicate_saln_for_same_year(): void
    {
        $user = $this->employeeUser();
        $this->makeSaln($user, ['year' => 2024]);

        $this->actingAs($user)
            ->post(route('saln.store'), [
                'year'       => 2024,
                'as_of_date' => '2024-12-31',
            ])
            ->assertRedirect(route('saln.show', SalnRecord::where('user_id', $user->id)->first()->id));
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)
            ->post(route('saln.store'), [])
            ->assertSessionHasErrors(['year', 'as_of_date']);
    }

    // ─── Show ──────────────────────────────────────────────────────────────────

    public function test_employee_can_view_own_saln(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user);

        $this->actingAs($user)
            ->get(route('saln.show', $saln))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('SALN/Show'));
    }

    public function test_employee_cannot_view_another_users_saln(): void
    {
        $owner = $this->employeeUser();
        $other = $this->employeeUser();
        $saln  = $this->makeSaln($owner);

        $this->actingAs($other)
            ->get(route('saln.show', $saln))
            ->assertForbidden();
    }

    // ─── Update (header) ───────────────────────────────────────────────────────

    public function test_employee_can_update_draft_saln_header(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user);

        $this->actingAs($user)
            ->put(route('saln.update', $saln), [
                'year'        => 2024,
                'as_of_date'  => '2024-12-31',
                'spouse_name' => 'Jane Doe',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('saln_records', [
            'id'          => $saln->id,
            'spouse_name' => 'Jane Doe',
        ]);
    }

    public function test_employee_cannot_update_submitted_saln(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user, ['status' => 'submitted']);

        $this->actingAs($user)
            ->put(route('saln.update', $saln), [
                'year'       => 2024,
                'as_of_date' => '2024-12-31',
            ])
            ->assertForbidden();
    }

    // ─── Delete ────────────────────────────────────────────────────────────────

    public function test_employee_can_delete_draft_saln(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user);

        $this->actingAs($user)
            ->delete(route('saln.destroy', $saln))
            ->assertRedirect(route('saln.index'));

        $this->assertDatabaseMissing('saln_records', ['id' => $saln->id]);
    }

    public function test_employee_cannot_delete_submitted_saln(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user, ['status' => 'submitted']);

        $this->actingAs($user)
            ->delete(route('saln.destroy', $saln))
            ->assertForbidden();
    }

    // ─── Submit ────────────────────────────────────────────────────────────────

    public function test_employee_can_submit_draft_saln(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user);

        $this->actingAs($user)
            ->post(route('saln.submit', $saln))
            ->assertRedirect();

        $this->assertDatabaseHas('saln_records', [
            'id'     => $saln->id,
            'status' => 'submitted',
        ]);
    }

    public function test_employee_cannot_submit_already_submitted_saln(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user, ['status' => 'submitted']);

        $this->actingAs($user)
            ->post(route('saln.submit', $saln))
            ->assertRedirect(); // back() with error flash

        // Status should remain submitted, not change
        $this->assertDatabaseHas('saln_records', ['id' => $saln->id, 'status' => 'submitted']);
    }

    public function test_submit_creates_audit_log_entry(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user);

        $this->actingAs($user)->post(route('saln.submit', $saln));

        $this->assertDatabaseHas('saln_reviews', [
            'saln_record_id' => $saln->id,
            'actor_id'       => $user->id,
            'action'         => 'submitted',
        ]);
    }

    // ─── Real Properties ───────────────────────────────────────────────────────

    public function test_employee_can_add_real_property_to_draft(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user);

        $this->actingAs($user)
            ->post(route('saln.real-properties.store', $saln), [
                'kind'                      => 'Residential Lot',
                'exact_location'            => 'Butuan City, Agusan del Norte',
                'current_fair_market_value' => 500000,
                'acquisition_mode'          => 'purchased',
                'owner'                     => 'self',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('saln_real_properties', [
            'saln_record_id'            => $saln->id,
            'kind'                      => 'Residential Lot',
            'current_fair_market_value' => 500000,
        ]);
    }

    public function test_adding_real_property_recomputes_totals(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user);

        $this->actingAs($user)
            ->post(route('saln.real-properties.store', $saln), [
                'kind'                      => 'Lot',
                'exact_location'            => 'Cebu City',
                'current_fair_market_value' => 1000000,
                'acquisition_mode'          => 'purchased',
                'owner'                     => 'self',
            ]);

        $saln->refresh();
        $this->assertEquals(1000000, $saln->total_real_properties);
        $this->assertEquals(1000000, $saln->total_assets);
        $this->assertEquals(1000000, $saln->net_worth);
    }

    public function test_employee_cannot_add_property_to_submitted_saln(): void
    {
        $user = $this->employeeUser();
        $saln = $this->makeSaln($user, ['status' => 'submitted']);

        $this->actingAs($user)
            ->post(route('saln.real-properties.store', $saln), [
                'kind'                      => 'Lot',
                'exact_location'            => 'Cebu',
                'current_fair_market_value' => 100000,
                'acquisition_mode'          => 'purchased',
                'owner'                     => 'self',
            ])
            ->assertForbidden();
    }
}
