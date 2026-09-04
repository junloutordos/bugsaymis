<?php

namespace Tests\Feature\OPCR;

use App\Models\Division;
use App\Models\OPCR\OpcrIndicator;
use App\Models\OPCR\OpcrPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrPeriodIndexTest extends TestCase
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

    public function test_manage_user_sees_all_indicators_in_the_current_period(): void
    {
        $user = $this->userWithPermission('OCD', ['opcr.view', 'opcr.manage']);
        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026', 'is_current' => true]);
        $divisionA = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $divisionB = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $i1 = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator 1']);
        $i1->divisions()->sync([$divisionA->id]);
        $i2 = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Indicator 2']);
        $i2->divisions()->sync([$divisionB->id]);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Opcr', false)
            ->where('period.id', $period->id)
            ->has('indicators', 2)
            ->where('canManage', true)
        );
    }

    public function test_division_chief_sees_only_their_divisions_indicators_and_cannot_manage(): void
    {
        $user = $this->userWithPermission('DivisionChief', ['opcr.view']);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID']);
        $otherDivision = Division::create(['division_name' => 'FAD', 'acronym' => 'FAD']);
        $user->update(['division_id' => $division->id]);

        $period = OpcrPeriod::create(['fiscal_year' => 2026, 'period_label' => 'FY2026', 'is_current' => true]);
        $mine = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Mine']);
        $mine->divisions()->sync([$division->id]);
        $notMine = OpcrIndicator::create(['opcr_period_id' => $period->id, 'description' => 'Not mine']);
        $notMine->divisions()->sync([$otherDivision->id]);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Opcr', false)
            ->has('indicators', 1)
            ->where('indicators.0.id', $mine->id)
            ->where('canManage', false)
        );
    }

    public function test_index_renders_with_no_current_period(): void
    {
        $user = $this->userWithPermission('OCD', ['opcr.view', 'opcr.manage']);

        $response = $this->actingAs($user)->get(route('opcr.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PerformanceManagement/Opcr', false)
            ->where('period', null)
            ->has('indicators', 0)
        );
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
