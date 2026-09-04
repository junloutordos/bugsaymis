<?php

namespace Tests\Feature\OPCR;

use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPeriodCrudTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        Permission::firstOrCreate(['name' => 'opcr.view'], ['module' => 'OPCR', 'description' => 'opcr.view']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_store_creates_a_new_period_not_marked_current(): void
    {
        $user = $this->manager();

        $response = $this->actingAs($user)->post(route('opcr-periods.store'), [
            'fiscal_year' => 2027,
            'period_label' => 'January - December 2027',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opcr_periods', ['fiscal_year' => 2027, 'is_current' => false]);
    }

    public function test_store_rejects_duplicate_fiscal_year(): void
    {
        $user = $this->manager();
        OpcrPeriod::create(['fiscal_year' => 2027, 'period_label' => 'FY2027']);

        $response = $this->actingAs($user)->post(route('opcr-periods.store'), [
            'fiscal_year' => 2027,
            'period_label' => 'Duplicate',
        ]);

        $response->assertSessionHasErrors('fiscal_year');
    }

    public function test_update_marking_a_period_current_unmarks_all_others(): void
    {
        $user = $this->manager();
        $old = OpcrPeriod::create(['fiscal_year' => 2025, 'period_label' => 'FY2025', 'is_current' => true]);
        $new = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026', 'is_current' => false]);

        $response = $this->actingAs($user)->put(route('opcr-periods.update', $new), [
            'fiscal_year' => 2026,
            'period_label' => 'FY2026',
            'is_current' => true,
            'campus_director_name' => 'RAMIL A. SANCHEZ',
        ]);

        $response->assertRedirect();
        $this->assertTrue($new->fresh()->is_current);
        $this->assertFalse($old->fresh()->is_current);
        $this->assertEquals('RAMIL A. SANCHEZ', $new->fresh()->campus_director_name);
    }

    public function test_view_only_user_cannot_store_a_period(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.view'], ['module' => 'OPCR', 'description' => 'opcr.view']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->post(route('opcr-periods.store'), [
            'fiscal_year' => 2027,
            'period_label' => 'FY2027',
        ]);

        $response->assertForbidden();
    }
}
