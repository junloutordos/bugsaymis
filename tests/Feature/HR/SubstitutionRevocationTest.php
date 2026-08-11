<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubstitutionRevocationTest extends TestCase
{
    use RefreshDatabase;

    private SubstitutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubstitutionService::class);
    }

    private function approvedSubstitution(): array
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
            'date_from' => now()->addDays(5)->toDateString(), 'date_to' => now()->addDays(7)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);
        $substitution = $this->service->nominate($original, $substitute, $leave);
        $substitution = $this->service->approve($substitution, $chief);

        return [$substitution, $original, $chief, $leave];
    }

    public function test_original_user_can_revoke_own_grant(): void
    {
        [$substitution, $original] = $this->approvedSubstitution();

        $result = $this->service->revoke($substitution, $original, 'Leave cut short');

        $this->assertSame('revoked', $result->status);
        $this->assertSame($original->id, $result->revoked_by);
        $this->assertSame('Leave cut short', $result->revocation_reason);
    }

    public function test_unrelated_user_cannot_revoke(): void
    {
        [$substitution] = $this->approvedSubstitution();
        $stranger = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->revoke($substitution, $stranger, 'no reason');
    }

    public function test_revoke_for_cancelled_absence_finds_and_revokes_grant(): void
    {
        [$substitution, , , $leave] = $this->approvedSubstitution();

        $this->service->revokeForCancelledAbsence($leave);

        $this->assertSame('revoked', $substitution->fresh()->status);
        $this->assertNull($substitution->fresh()->revoked_by);
    }

    public function test_leave_cancellation_cascades_to_revoke_substitution(): void
    {
        [$substitution, $original, , $leave] = $this->approvedSubstitution();

        $this->actingAs($original)
            ->post(route('hr.leave.cancel', $leave))
            ->assertRedirect();

        $this->assertSame('revoked', $substitution->fresh()->status);
    }
}
