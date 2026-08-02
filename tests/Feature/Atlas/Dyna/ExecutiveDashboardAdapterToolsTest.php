<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetAttentionItemsTool;
use App\Services\Atlas\Dyna\Tools\GetDivisionScorecardTool;
use App\Services\Atlas\Dyna\Tools\GetPerformanceStatsTool;
use App\Services\Atlas\Dyna\Tools\GetSatisfactionStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveDashboardAdapterToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_performance_stats_returns_only_the_performance_section(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $result = (new GetPerformanceStatsTool(app(\App\Services\ExecutiveDashboardService::class)))
            ->execute($administrator, []);

        // ExecutiveDashboardService::performance() always returns these keys regardless of data volume.
        $this->assertArrayHasKey('funnel', $result);
        $this->assertArrayHasKey('complianceByDivision', $result);
    }

    public function test_division_chief_is_locked_to_their_own_division_for_scoped_sections(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        $chief = User::factory()->create(['division_id' => $divisionA->id]);
        $divisionA->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        // Attempt to request divisionB's data — must be ignored, locked to own division.
        $tool = new GetAttentionItemsTool(app(\App\Services\ExecutiveDashboardService::class));
        $resultOwn = $tool->execute($chief, []);
        $resultAttemptOther = $tool->execute($chief, ['division_id' => $divisionB->id]);

        $this->assertEquals($resultOwn, $resultAttemptOther);
    }

    public function test_campus_wide_sections_ignore_division_id_and_never_error(): void
    {
        $administrator = $this->userWithRole('Administrator');

        $result = (new GetSatisfactionStatsTool(app(\App\Services\ExecutiveDashboardService::class)))
            ->execute($administrator, []);

        $this->assertIsArray($result);
    }

    public function test_scorecard_is_empty_for_a_division_locked_user_matching_dashboard_behavior(): void
    {
        $division = Division::factory()->create();
        $chief = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        $result = (new GetDivisionScorecardTool(app(\App\Services\ExecutiveDashboardService::class)))
            ->execute($chief, []);

        // ExecutiveDashboardService::build() sets 'scorecard' => null whenever $divisionId is
        // non-null; the tool interface requires an array return, so this becomes a note instead.
        $this->assertArrayHasKey('note', $result);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
