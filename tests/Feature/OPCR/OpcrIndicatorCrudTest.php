<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\OPCR\OpcrIndicator;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrIndicatorCrudTest extends TestCase
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

    public function test_store_creates_an_indicator_with_tagging_and_divisions(): void
    {
        $user = $this->manager();
        $pillar = DostPillar::create(['name' => 'Pillar 1']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $subStrategy = DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Sub 1']);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'fiscal_year' => 2026,
            'dost_sub_strategy_id' => $subStrategy->id,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Percentage of graduates pursuing STEM',
            'target' => '0.9',
            'budget' => 15000,
            'remarks' => 'Notes',
            'division_ids' => [$division->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opcr_indicators', [
            'fiscal_year' => 2026,
            'dost_sub_strategy_id' => $subStrategy->id,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Percentage of graduates pursuing STEM',
        ]);
        $indicator = OpcrIndicator::firstWhere('description', 'Percentage of graduates pursuing STEM');
        $this->assertTrue($indicator->divisions->contains($division));
    }

    public function test_store_allows_no_dost_tagging_as_long_as_a_program_is_set(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Untagged indicator',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opcr_indicators', ['description' => 'Untagged indicator', 'agency_outcome_id' => $outcome->id]);
    }

    public function test_store_rejects_missing_description(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
        ]);

        $response->assertSessionHasErrors('description');
    }

    public function test_store_rejects_missing_fiscal_year(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'description' => 'Indicator',
            'agency_outcome_id' => $outcome->id,
        ]);

        $response->assertSessionHasErrors('fiscal_year');
    }

    public function test_store_rejects_missing_program(): void
    {
        $user = $this->manager();

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'fiscal_year' => 2026,
            'description' => 'Indicator',
        ]);

        $response->assertSessionHasErrors('agency_outcome_id');
    }

    public function test_update_resyncs_divisions(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $divisionA = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $divisionB = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);
        $indicator->divisions()->sync([$divisionA->id]);

        $response = $this->actingAs($user)->put(route('opcr-indicators.update', $indicator), [
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Indicator updated',
            'division_ids' => [$divisionB->id],
        ]);

        $response->assertRedirect();
        $fresh = $indicator->fresh();
        $this->assertEquals('Indicator updated', $fresh->description);
        $this->assertFalse($fresh->divisions->contains($divisionA));
        $this->assertTrue($fresh->divisions->contains($divisionB));
    }

    public function test_destroy_deletes_the_indicator(): void
    {
        $user = $this->manager();
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $indicator = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Indicator']);

        $response = $this->actingAs($user)->delete(route('opcr-indicators.destroy', $indicator));

        $response->assertRedirect();
        $this->assertDatabaseMissing('opcr_indicators', ['id' => $indicator->id]);
    }

    public function test_view_only_user_cannot_store(): void
    {
        $role = Role::create(['name' => 'DivisionChief']);
        $permission = Permission::firstOrCreate(['name' => 'opcr.view'], ['module' => 'OPCR', 'description' => 'opcr.view']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);

        $response = $this->actingAs($user)->post(route('opcr-indicators.store'), [
            'fiscal_year' => 2026,
            'agency_outcome_id' => $outcome->id,
            'description' => 'Indicator',
        ]);

        $response->assertForbidden();
    }
}
