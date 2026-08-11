<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActingAsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSubstitution()
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $substitute = User::factory()->create();
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

        return [$substitution, $original, $substitute];
    }

    public function test_substitute_can_start_acting_as(): void
    {
        [$substitution, $original, $substitute] = $this->approvedSubstitution();

        $this->actingAs($substitute)
            ->post(route('hr.substitutions.act-as.start', $substitution))
            ->assertRedirect();

        $this->assertAuthenticatedAs($original);
    }

    public function test_non_substitute_cannot_start_acting_as(): void
    {
        [$substitution] = $this->approvedSubstitution();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('hr.substitutions.act-as.start', $substitution))
            ->assertSessionHasErrors();

        $this->assertAuthenticatedAs($stranger);
    }

    public function test_exit_returns_to_true_user(): void
    {
        [$substitution, $original, $substitute] = $this->approvedSubstitution();
        $this->actingAs($substitute)->post(route('hr.substitutions.act-as.start', $substitution));

        $this->post(route('hr.substitutions.act-as.exit'))->assertRedirect();

        $this->assertAuthenticatedAs($substitute);
    }
}
