<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\HR\Substitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubstitutionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setUpDivisionAndLeave(): array
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division '.uniqid(), 'acronym' => 'TD'.uniqid(),
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $substitute = User::factory()->create();
        $leaveType = LeaveType::firstOrCreate(
            ['code' => 'VL'],
            [
                'name' => 'Vacation Leave', 'days_per_year' => 15,
                'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
                'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
            ]
        );
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->addDays(5)->toDateString(), 'date_to' => now()->addDays(7)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);

        return [$original, $substitute, $chief, $leave];
    }

    public function test_employee_can_nominate_a_substitute_for_their_own_leave(): void
    {
        [$original, $substitute, , $leave] = $this->setUpDivisionAndLeave();

        $this->actingAs($original)
            ->post(route('hr.substitutions.store'), [
                'substitute_user_id' => $substitute->id,
                'leave_application_id' => $leave->id,
                'notes' => 'Please cover my Grade 10 sections',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('substitutions', [
            'original_user_id' => $original->id,
            'substitute_user_id' => $substitute->id,
            'status' => 'pending_approval',
        ]);
    }

    public function test_employee_cannot_nominate_a_substitute_for_someone_elses_leave(): void
    {
        [$original, $substitute] = $this->setUpDivisionAndLeave();
        $intruder = User::factory()->create();
        [, , , $otherLeave] = $this->setUpDivisionAndLeave();

        $this->actingAs($intruder)
            ->post(route('hr.substitutions.store'), [
                'substitute_user_id' => $substitute->id,
                'leave_application_id' => $otherLeave->id,
            ])
            ->assertStatus(403);
    }

    public function test_resolved_approver_can_approve_via_http(): void
    {
        [$original, $substitute, $chief, $leave] = $this->setUpDivisionAndLeave();
        $substitution = app(\App\Services\HR\SubstitutionService::class)->nominate($original, $substitute, $leave);

        $this->actingAs($chief)
            ->post(route('hr.substitutions.approve', $substitution))
            ->assertRedirect();

        $this->assertSame('approved', $substitution->fresh()->status);
    }

    public function test_original_user_can_revoke_via_http(): void
    {
        [$original, $substitute, $chief, $leave] = $this->setUpDivisionAndLeave();
        $service = app(\App\Services\HR\SubstitutionService::class);
        $substitution = $service->approve($service->nominate($original, $substitute, $leave), $chief);

        $this->actingAs($original)
            ->post(route('hr.substitutions.revoke', $substitution), ['reason' => 'No longer needed'])
            ->assertRedirect();

        $this->assertSame('revoked', $substitution->fresh()->status);
    }

    public function test_index_lists_my_nominations_and_my_substitutions(): void
    {
        [$original, $substitute, , $leave] = $this->setUpDivisionAndLeave();
        app(\App\Services\HR\SubstitutionService::class)->nominate($original, $substitute, $leave);

        $response = $this->actingAs($original)->get(route('hr.substitutions.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('HR/Substitutions/Index', false)
            ->has('myNominations', 1)
        );

        $response2 = $this->actingAs($substitute)->get(route('hr.substitutions.index'));
        $response2->assertInertia(fn ($page) => $page->has('mySubstitutions', 1));
    }
}
