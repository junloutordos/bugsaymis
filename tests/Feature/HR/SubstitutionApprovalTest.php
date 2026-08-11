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

class SubstitutionApprovalTest extends TestCase
{
    use RefreshDatabase;

    private SubstitutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubstitutionService::class);
    }

    private function pendingSubstitution(): array
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

        return [$substitution, $chief, $original];
    }

    public function test_resolved_approver_can_approve(): void
    {
        [$substitution, $chief] = $this->pendingSubstitution();

        $result = $this->service->approve($substitution, $chief, 'Looks good');

        $this->assertSame('approved', $result->status);
        $this->assertSame($chief->id, $result->approved_by);
        $this->assertNotNull($result->approved_at);
    }

    public function test_non_approver_cannot_approve(): void
    {
        [$substitution] = $this->pendingSubstitution();
        $stranger = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->approve($substitution, $stranger);
    }

    public function test_already_decided_substitution_cannot_be_approved_again(): void
    {
        [$substitution, $chief] = $this->pendingSubstitution();
        $this->service->approve($substitution, $chief);

        $this->expectException(ValidationException::class);
        $this->service->approve($substitution->fresh(), $chief);
    }

    public function test_resolved_approver_can_reject_with_reason(): void
    {
        [$substitution, $chief] = $this->pendingSubstitution();

        $result = $this->service->reject($substitution, $chief, 'Not appropriate coverage');

        $this->assertSame('rejected', $result->status);
        $this->assertSame('Not appropriate coverage', $result->rejection_reason);
    }
}
