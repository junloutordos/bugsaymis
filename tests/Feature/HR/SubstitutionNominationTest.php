<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\HR\Substitution;
use App\Models\Role;
use App\Models\User;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubstitutionNominationTest extends TestCase
{
    use RefreshDatabase;

    private SubstitutionService $service;
    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubstitutionService::class);
        $this->leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
    }

    private function approvedLeave(User $user, array $overrides = []): LeaveApplication
    {
        return LeaveApplication::create(array_merge([
            'user_id' => $user->id,
            'leave_type_id' => $this->leaveType->id,
            'date_from' => now()->addDays(5)->toDateString(),
            'date_to' => now()->addDays(7)->toDateString(),
            'dates' => [],
            'days_applied' => 3,
            'status' => 'approved',
        ], $overrides));
    }

    public function test_nominate_creates_pending_approval_substitution(): void
    {
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD', 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => User::factory()->create()->id]);
        $substitute = User::factory()->create();
        $leave = $this->approvedLeave($original);

        $substitution = $this->service->nominate($original, $substitute, $leave);

        $this->assertSame('pending_approval', $substitution->status);
        $this->assertSame($original->id, $substitution->original_user_id);
        $this->assertSame($substitute->id, $substitution->substitute_user_id);
        $this->assertSame($leave->date_from->toDateString(), $substitution->start_date->toDateString());
        $this->assertSame($leave->date_to->toDateString(), $substitution->end_date->toDateString());
    }

    public function test_nominate_blocks_superadmin_original_user(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'Administrator'])->id);
        $substitute = User::factory()->create();
        $leave = $this->approvedLeave($admin);

        $this->expectException(ValidationException::class);
        $this->service->nominate($admin, $substitute, $leave);
    }

    public function test_nominate_blocks_self_substitution(): void
    {
        $original = User::factory()->create();
        $leave = $this->approvedLeave($original);

        $this->expectException(ValidationException::class);
        $this->service->nominate($original, $original, $leave);
    }

    public function test_nominate_blocks_substitute_with_overlapping_own_leave(): void
    {
        $original = User::factory()->create();
        $substitute = User::factory()->create();
        $this->approvedLeave($substitute, [
            'date_from' => now()->addDays(6)->toDateString(),
            'date_to' => now()->addDays(6)->toDateString(),
        ]);
        $leave = $this->approvedLeave($original);

        $this->expectException(ValidationException::class);
        $this->service->nominate($original, $substitute, $leave);
    }

    public function test_nominate_blocks_overlapping_grant_for_same_original_user(): void
    {
        $original = User::factory()->create();
        $substituteA = User::factory()->create();
        $substituteB = User::factory()->create();
        $leave = $this->approvedLeave($original);

        $this->service->nominate($original, $substituteA, $leave);

        $this->expectException(ValidationException::class);
        $this->service->nominate($original, $substituteB, $leave);
    }
}
