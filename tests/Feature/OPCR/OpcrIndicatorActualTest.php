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

class OpcrIndicatorActualTest extends TestCase
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

    public function test_setting_q1_then_q2_creates_two_rows(): void
    {
        $user = $this->manager();
        $indicator = $this->indicator();

        $this->actingAs($user)->put(route('opcr-indicators.actual', $indicator), ['quarter' => 1, 'value' => '0.5'])->assertRedirect();
        $this->actingAs($user)->put(route('opcr-indicators.actual', $indicator), ['quarter' => 2, 'value' => '0.7'])->assertRedirect();

        $this->assertCount(2, $indicator->fresh()->actuals);
    }

    public function test_resubmitting_the_same_quarter_updates_in_place(): void
    {
        $user = $this->manager();
        $indicator = $this->indicator();
        OpcrIndicatorActual::create(['opcr_indicator_id' => $indicator->id, 'quarter' => 1, 'value' => '0.5']);

        $this->actingAs($user)->put(route('opcr-indicators.actual', $indicator), ['quarter' => 1, 'value' => '0.6'])->assertRedirect();

        $this->assertCount(1, $indicator->fresh()->actuals);
        $this->assertEquals('0.6', $indicator->actuals()->where('quarter', 1)->first()->value);
    }

    public function test_quarter_must_be_between_1_and_4(): void
    {
        $user = $this->manager();
        $indicator = $this->indicator();

        $response = $this->actingAs($user)->put(route('opcr-indicators.actual', $indicator), ['quarter' => 5, 'value' => '0.6']);

        $response->assertSessionHasErrors('quarter');
    }
}
