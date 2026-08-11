<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActingAsInertiaPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_acting_as_prop_is_null_normally(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('actingAs', null));
    }

    public function test_acting_as_prop_populated_during_active_session(): void
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id, 'name' => 'Original Person']);
        $substitute = User::factory()->create(['name' => 'Substitute Person']);
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);
        $service = app(SubstitutionService::class);
        $substitution = $service->approve($service->nominate($original, $substitute, $leave), $chief);

        $this->actingAs($substitute)->post(route('hr.substitutions.act-as.start', $substitution));

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('actingAs.original_user_name', 'Original Person')
            ->where('actingAs.substitute_user_name', 'Substitute Person')
        );
    }
}
