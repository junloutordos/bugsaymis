<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use App\Models\DostPillar;
use App\Models\DostStrategy;
use App\Models\DostSubStrategy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DostChainControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function skip(): array
    {
        return ['mode' => 'skip'];
    }

    public function test_creates_a_full_new_chain_and_tags_pillar_and_strategy_to_the_program(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('dost-chain.store'), [
            'pillar' => ['mode' => 'new', 'name' => 'DOST Pillar 1: Human Well-Being', 'outcome_statement' => 'Statement'],
            'strategy' => ['mode' => 'new', 'name' => 'Strategy 1'],
            'sub_strategy' => ['mode' => 'new', 'description' => 'Institutionalize FORWARD Framework'],
            'program' => ['mode' => 'new', 'outcome' => 'A. STEM Secondary Education', 'function_type' => 'Strategic Functions'],
        ]);

        $response->assertOk();
        $ids = $response->json();

        $pillar = DostPillar::findOrFail($ids['pillar_id']);
        $strategy = DostStrategy::findOrFail($ids['strategy_id']);
        $subStrategy = DostSubStrategy::findOrFail($ids['sub_strategy_id']);
        $program = AgencyOutcome::findOrFail($ids['agency_outcome_id']);

        $this->assertEquals($pillar->id, $strategy->dost_pillar_id);
        $this->assertEquals($strategy->id, $subStrategy->dost_strategy_id);
        $this->assertTrue($pillar->agencyOutcomes->contains($program));
        $this->assertTrue($strategy->agencyOutcomes->contains($program));
    }

    public function test_reuses_existing_pillar_and_strategy_when_selected(): void
    {
        $admin = $this->admin();
        $pillar = DostPillar::create(['name' => 'Existing Pillar']);
        $strategy = DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Existing Strategy']);

        $response = $this->actingAs($admin)->postJson(route('dost-chain.store'), [
            'pillar' => ['mode' => 'existing', 'id' => $pillar->id],
            'strategy' => ['mode' => 'existing', 'id' => $strategy->id],
            'sub_strategy' => ['mode' => 'new', 'description' => 'New sub-strategy under existing strategy'],
            'program' => $this->skip(),
        ]);

        $response->assertOk();
        $ids = $response->json();

        $this->assertEquals($pillar->id, $ids['pillar_id']);
        $this->assertEquals($strategy->id, $ids['strategy_id']);
        $this->assertNull($ids['agency_outcome_id']);
        $this->assertEquals(1, DostPillar::count());
        $this->assertEquals(1, DostStrategy::count());
    }

    public function test_skipping_all_levels_but_program_only_creates_the_program(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('dost-chain.store'), [
            'pillar' => $this->skip(),
            'strategy' => $this->skip(),
            'sub_strategy' => $this->skip(),
            'program' => ['mode' => 'new', 'outcome' => 'Standalone Program', 'function_type' => 'Strategic Functions'],
        ]);

        $response->assertOk();
        $ids = $response->json();

        $this->assertNull($ids['pillar_id']);
        $this->assertNull($ids['strategy_id']);
        $this->assertNull($ids['sub_strategy_id']);
        $this->assertNotNull($ids['agency_outcome_id']);
    }

    public function test_creating_a_new_strategy_without_a_resolved_pillar_fails(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('dost-chain.store'), [
            'pillar' => $this->skip(),
            'strategy' => ['mode' => 'new', 'name' => 'Orphan Strategy'],
            'sub_strategy' => $this->skip(),
            'program' => $this->skip(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('pillar.mode');
        $this->assertEquals(0, DostStrategy::count());
    }

    public function test_creating_a_new_sub_strategy_without_a_resolved_strategy_fails(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('dost-chain.store'), [
            'pillar' => $this->skip(),
            'strategy' => $this->skip(),
            'sub_strategy' => ['mode' => 'new', 'description' => 'Orphan Sub-Strategy'],
            'program' => $this->skip(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('strategy.mode');
        $this->assertEquals(0, DostSubStrategy::count());
    }

    public function test_a_failed_chain_does_not_leave_partial_rows_behind(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('dost-chain.store'), [
            'pillar' => ['mode' => 'new', 'name' => 'Should be rolled back'],
            'strategy' => $this->skip(),
            'sub_strategy' => ['mode' => 'new', 'description' => 'Fails: no strategy'],
            'program' => $this->skip(),
        ]);

        $this->assertEquals(0, DostPillar::count());
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->postJson(route('dost-chain.store'), [
            'pillar' => $this->skip(),
            'strategy' => $this->skip(),
            'sub_strategy' => $this->skip(),
            'program' => $this->skip(),
        ]);

        $response->assertForbidden();
    }
}
