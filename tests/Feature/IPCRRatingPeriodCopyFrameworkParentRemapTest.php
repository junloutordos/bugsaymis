<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IPCRRatingPeriodCopyFrameworkParentRemapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_copying_a_fiscal_year_remaps_parent_id_to_the_new_years_clone(): void
    {
        $admin = $this->admin();
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions', 'fiscal_year' => 2026]);
        AgencyOutcome::create(['outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'fiscal_year' => 2026, 'parent_id' => $parent->id]);

        $response = $this->actingAs($admin)->post(route('ipcr-rating-periods.copyFramework'), [
            'source_year' => 2026,
            'target_year' => 2027,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $newParent = AgencyOutcome::where('fiscal_year', 2027)->whereNull('parent_id')->firstOrFail();
        $newChild = AgencyOutcome::where('fiscal_year', 2027)->where('sub_outcome', 'A.1')->firstOrFail();

        $this->assertEquals($newParent->id, $newChild->parent_id);
        $this->assertNotEquals($parent->id, $newChild->parent_id);
    }
}
