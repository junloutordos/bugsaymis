<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostStrategyControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function userWithoutIpcrView(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_administrator_can_create_a_strategy_without_any_programs(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'DOST Pillar 1: Human Well-Being']);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'Strategy 1: Achieve quality science education',
        ]);

        $response->assertRedirect();
        $strategy = DostStrategy::firstWhere('name', 'Strategy 1: Achieve quality science education');
        $this->assertEquals($pillar->id, $strategy->dost_pillar_id);
        $this->assertCount(0, $strategy->agencyOutcomes);
    }

    public function test_administrator_can_create_a_strategy_linked_to_multiple_programs(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'DOST Pillar 5: Governance']);
        $programA = AgencyOutcome::create(['outcome' => 'B. STEM Promotion Program']);
        $programB = AgencyOutcome::create(['outcome' => 'C. General Administration and Support']);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'Strategy 17: Institutionalize science communication',
            'agency_outcome_ids' => [$programA->id, $programB->id],
        ]);

        $response->assertRedirect();
        $strategy = DostStrategy::firstWhere('name', 'Strategy 17: Institutionalize science communication');
        $this->assertTrue($strategy->agencyOutcomes->contains($programA));
        $this->assertTrue($strategy->agencyOutcomes->contains($programB));
    }

    public function test_the_same_strategy_can_be_tagged_to_a_program_that_another_strategy_also_uses(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar A']);
        $program = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);
        $existing = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Existing Strategy']);
        $existing->agencyOutcomes()->sync([$program->id]);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'Second Strategy',
            'agency_outcome_ids' => [$program->id],
        ]);

        $response->assertRedirect();
        $this->assertCount(2, $program->fresh()->dostStrategies);
    }

    public function test_creating_a_strategy_requires_a_valid_pillar(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => 99999,
            'name' => 'Orphan Strategy',
        ]);

        $response->assertSessionHasErrors('dost_pillar_id');
        $this->assertDatabaseMissing('dost_strategies', ['name' => 'Orphan Strategy']);
    }

    public function test_creating_a_strategy_rejects_a_nonexistent_program(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'DOST Pillar 2: Wealth Creation']);

        $response = $this->actingAs($admin)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'Strategy 5: Advance research',
            'agency_outcome_ids' => [99999],
        ]);

        $response->assertSessionHasErrors('agency_outcome_ids.0');
    }

    public function test_administrator_can_update_a_strategy_and_its_program_tags(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar A']);
        $programA = AgencyOutcome::create(['outcome' => 'Program A']);
        $programB = AgencyOutcome::create(['outcome' => 'Program B']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Old Name']);
        $strategy->agencyOutcomes()->sync([$programA->id]);

        $response = $this->actingAs($admin)->put(route('dost-strategies.update', $strategy), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'New Name',
            'agency_outcome_ids' => [$programB->id],
        ]);

        $response->assertRedirect();
        $fresh = $strategy->fresh();
        $this->assertEquals('New Name', $fresh->name);
        $this->assertFalse($fresh->agencyOutcomes->contains($programA));
        $this->assertTrue($fresh->agencyOutcomes->contains($programB));
    }

    public function test_administrator_can_delete_a_strategy(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Pillar A']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'To Delete']);

        $response = $this->actingAs($admin)->delete(route('dost-strategies.destroy', $strategy));

        $response->assertRedirect();
        $this->assertDatabaseMissing('dost_strategies', ['id' => $strategy->id]);
    }

    public function test_user_without_ipcr_view_permission_cannot_create_a_strategy(): void
    {
        $user = $this->userWithoutIpcrView();
        $pillar = DostPillar::create(['name' => 'Pillar A']);

        $response = $this->actingAs($user)->post(route('dost-strategies.store'), [
            'dost_pillar_id' => $pillar->id,
            'name' => 'Blocked Strategy',
        ]);

        $response->assertForbidden();
    }
}
