<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\OPCR\OpcrIndicator;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrIndicatorRatingTest extends TestCase
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

    public function test_rating_saves_exactly_what_is_submitted_without_recomputing_average(): void
    {
        $user = $this->manager();
        $indicator = $this->indicator();

        $response = $this->actingAs($user)->put(route('opcr-indicators.rating', $indicator), [
            'rating_quality' => 5,
            'rating_efficiency' => 4,
            'rating_timeliness' => 3,
            'rating_average' => 4.5, // deliberately NOT (5+4+3)/3, to prove it's never recomputed
        ]);

        $response->assertRedirect();
        $fresh = $indicator->fresh();
        $this->assertEquals(5, $fresh->rating_quality);
        $this->assertEquals(4, $fresh->rating_efficiency);
        $this->assertEquals(3, $fresh->rating_timeliness);
        $this->assertEquals(4.5, $fresh->rating_average);
    }

    public function test_rating_values_must_be_between_1_and_5(): void
    {
        $user = $this->manager();
        $indicator = $this->indicator();

        $response = $this->actingAs($user)->put(route('opcr-indicators.rating', $indicator), [
            'rating_quality' => 6,
        ]);

        $response->assertSessionHasErrors('rating_quality');
    }
}
