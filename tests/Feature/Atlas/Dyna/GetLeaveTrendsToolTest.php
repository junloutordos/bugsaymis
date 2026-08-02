<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetLeaveTrendsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetLeaveTrendsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_division_chief_only_sees_leave_applications_from_their_division(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();

        $inDivisionA = User::factory()->create(['division_id' => $divisionA->id]);
        $inDivisionB = User::factory()->create(['division_id' => $divisionB->id]);

        LeaveApplication::factory()->create(['user_id' => $inDivisionA->id, 'status' => 'approved', 'created_at' => '2026-07-15']);
        LeaveApplication::factory()->create(['user_id' => $inDivisionA->id, 'status' => 'pending', 'created_at' => '2026-07-20']);
        LeaveApplication::factory()->create(['user_id' => $inDivisionB->id, 'status' => 'approved', 'created_at' => '2026-07-18']);

        $chief = User::factory()->create(['division_id' => $divisionA->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        $result = (new GetLeaveTrendsTool())->execute($chief, [
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-31',
        ]);

        $this->assertEquals(['approved' => 1, 'pending' => 1], $result);
    }
}
