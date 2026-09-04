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

class OpcrIndexTest extends TestCase
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

    public function test_defaults_to_the_current_ipcr_rating_period_year(): void
    {
        $user = $this->userWithPermission('OCD', ['opcr.view', 'opcr.manage']);
        IPCRRatingPeriod::create(['label' => 'FY2025', 'year' => 2025, 'is_current' => false]);
        IPCRRatingPeriod::create(['label' => 'FY2026', 'year' => 2026, 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $outcome->id, 'description' => 'FY2025 indicator']);
        $fy2026 = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'FY2026 indicator']);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Opcr', false)
            ->where('currentFiscalYear', 2026)
            ->where('selectedFiscalYear', '2026')
            ->has('indicators', 1)
            ->where('indicators.0.id', $fy2026->id)
            ->where('canManage', true)
        );
    }

    public function test_fiscal_year_query_param_switches_the_selected_year(): void
    {
        $user = $this->userWithPermission('OCD', ['opcr.view', 'opcr.manage']);
        IPCRRatingPeriod::create(['label' => 'FY2025', 'year' => 2025, 'is_current' => false]);
        IPCRRatingPeriod::create(['label' => 'FY2026', 'year' => 2026, 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);
        $fy2025 = OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $outcome->id, 'description' => 'FY2025 indicator']);

        $response = $this->actingAs($user)->get(route('opcr.index', ['fiscal_year' => 2025]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('selectedFiscalYear', '2025')
            ->has('indicators', 1)
            ->where('indicators.0.id', $fy2025->id)
        );
    }

    public function test_division_chief_sees_only_their_divisions_indicators_and_cannot_manage(): void
    {
        $user = $this->userWithPermission('DivisionChief', ['opcr.view']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $otherDivision = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $user->update(['division_id' => $division->id]);
        IPCRRatingPeriod::create(['label' => 'FY2026', 'year' => 2026, 'is_current' => true]);
        $outcome = AgencyOutcome::create(['outcome' => 'A. STEM']);

        $mine = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Mine']);
        $mine->divisions()->sync([$division->id]);
        $notMine = OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $outcome->id, 'description' => 'Not mine']);
        $notMine->divisions()->sync([$otherDivision->id]);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('indicators', 1)
            ->where('indicators.0.id', $mine->id)
            ->where('canManage', false)
        );
    }

    public function test_index_renders_with_no_indicators_for_the_year(): void
    {
        $user = $this->userWithPermission('OCD', ['opcr.view', 'opcr.manage']);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('indicators', 0));
    }

    public function test_user_without_opcr_permission_gets_403(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'RegularStaffTester_'.uniqid()]);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertForbidden();
    }
}
