<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetHeadcountTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetHeadcountToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_campus_wide_headcount(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        User::factory()->count(2)->create(['division_id' => $divisionA->id, 'status' => 'active']);
        User::factory()->count(3)->create(['division_id' => $divisionB->id, 'status' => 'active']);
        $administrator = $this->userWithRole('Administrator');

        $result = (new GetHeadcountTool())->execute($administrator, []);

        $this->assertEquals(6, $result['total_headcount']); // 2 + 3 + the administrator user itself
    }

    public function test_division_chief_only_sees_their_own_division(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        User::factory()->count(2)->create(['division_id' => $divisionA->id, 'status' => 'active']);
        User::factory()->count(3)->create(['division_id' => $divisionB->id, 'status' => 'active']);
        $chief = $this->userWithRole('DivisionChief', ['division_id' => $divisionA->id]);

        $result = (new GetHeadcountTool())->execute($chief, []);

        $this->assertEquals(3, $result['total_headcount']); // 2 in division A + the chief themself
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $user->roles()->attach($role);

        return $user;
    }
}
