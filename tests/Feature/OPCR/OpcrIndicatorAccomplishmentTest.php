<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrIndicatorActual;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrIndicatorAccomplishmentTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $role = Role::create(['name' => 'OCD']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.manage'], ['module' => 'OPCR', 'description' => 'opcr.manage']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function indicator(): OpcrIndicator
    {
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);

        return OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);
    }

    public function test_manager_can_set_a_manual_accomplishment_override(): void
    {
        $user = $this->manager();
        $indicator = $this->indicator();

        $this->actingAs($user)
            ->put(route('opcr-indicators.accomplishment', $indicator), ['accomplishment' => 'Fully accomplished ahead of schedule'])
            ->assertRedirect();

        $this->assertSame('Fully accomplished ahead of schedule', $indicator->fresh()->accomplishment);
    }

    public function test_clearing_the_field_reverts_to_the_auto_summary(): void
    {
        $user = $this->manager();
        $indicator = $this->indicator();
        $indicator->update(['accomplishment' => 'Manually typed note']);
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '80%']);

        $this->actingAs($user)
            ->put(route('opcr-indicators.accomplishment', $indicator), ['accomplishment' => ''])
            ->assertRedirect();

        $fresh = OpcrIndicator::with('actuals')->find($indicator->id);
        $this->assertNull($fresh->accomplishment);
        $this->assertSame('Q1: 80%', $fresh->displayed_accomplishment);
    }
}
