<?php

namespace Database\Factories\HR;

use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\HR\Substitution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HR\Substitution>
 */
class SubstitutionFactory extends Factory
{
    protected $model = Substitution::class;

    public function definition(): array
    {
        $original = User::factory()->create();
        $leaveType = LeaveType::firstOrCreate(
            ['code' => 'VL'],
            [
                'name' => 'Vacation Leave', 'days_per_year' => 15,
                'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
                'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
            ]
        );
        $leave = LeaveApplication::create([
            'user_id' => $original->id,
            'leave_type_id' => $leaveType->id,
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(2)->toDateString(),
            'dates' => [],
            'days_applied' => 3,
            'status' => 'approved',
        ]);

        return [
            'original_user_id' => $original->id,
            'substitute_user_id' => User::factory(),
            'absentable_type' => LeaveApplication::class,
            'absentable_id' => $leave->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending_approval',
            'nominated_by' => $original->id,
        ];
    }
}
