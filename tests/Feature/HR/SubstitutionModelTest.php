<?php

namespace Tests\Feature\HR;

use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\HR\Substitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubstitutionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_within_window_true_when_today_between_start_and_end(): void
    {
        $original = User::factory()->create();
        $substitute = User::factory()->create();
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);

        $substitution = Substitution::create([
            'original_user_id' => $original->id,
            'substitute_user_id' => $substitute->id,
            'absentable_type' => LeaveApplication::class,
            'absentable_id' => $leave->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
            'nominated_by' => $original->id,
        ]);

        $this->assertTrue($substitution->isWithinWindow());
        $this->assertSame($original->id, $substitution->originalUser->id);
        $this->assertSame($substitute->id, $substitution->substitute->id);
        $this->assertInstanceOf(LeaveApplication::class, $substitution->absentable);
    }

    public function test_is_within_window_false_when_outside_dates(): void
    {
        $substitution = Substitution::factory()->create([
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertFalse($substitution->isWithinWindow());
    }
}
