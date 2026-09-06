<?php

namespace Tests\Feature\OPCR;

use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\IPCRRatingPeriod;
use App\Models\OPCR\OpcrIndicator;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrYearIndexTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $roleName, array $permNames): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        foreach ($permNames as $permName) {
            $permission = Permission::firstOrCreate(['name' => $permName], ['module' => 'OPCR', 'description' => $permName]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_no_fiscal_year_param_renders_the_year_index_with_counts_current_year_first(): void
    {
        $user = $this->userWithPermission('OCD', ['opcr.view', 'opcr.manage']);
        IPCRRatingPeriod::create(['label' => 'FY2025', 'year' => 2025, 'is_current' => false]);
        IPCRRatingPeriod::create(['label' => 'FY2026', 'year' => 2026, 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $outcome->id, 'description' => 'A']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'B']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'C']);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/OpcrIndex', false)
            ->where('currentFiscalYear', 2026)
            ->has('years', 2)
            ->where('years.0.year', 2026)
            ->where('years.0.indicator_count', 2)
            ->where('years.0.is_current', true)
            ->where('years.1.year', 2025)
            ->where('years.1.indicator_count', 1)
            ->where('years.1.is_current', false)
        );
    }

    public function test_current_year_is_listed_even_with_zero_indicators(): void
    {
        $user = $this->userWithPermission('OCD', ['opcr.view', 'opcr.manage']);
        IPCRRatingPeriod::create(['label' => 'FY2026', 'year' => 2026, 'is_current' => true]);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('years', 1)
            ->where('years.0.year', 2026)
            ->where('years.0.indicator_count', 0)
        );
    }

    public function test_division_chief_only_sees_years_where_their_division_has_indicators(): void
    {
        $user = $this->userWithPermission('DivisionChief', ['opcr.view']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $otherDivision = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $user->update(['division_id' => $division->id]);
        IPCRRatingPeriod::create(['label' => 'FY2026', 'year' => 2026, 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);

        $mine = OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $outcome->id, 'description' => 'Mine']);
        $mine->divisions()->sync([$division->id]);
        $notMine = OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $outcome->id, 'description' => 'Not mine']);
        $notMine->divisions()->sync([$otherDivision->id]);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('years', 2) // 2025 (their division has 1) + 2026 (current, always listed)
            ->where('years.1.year', 2025)
            ->where('years.1.indicator_count', 1)
        );
    }
}
