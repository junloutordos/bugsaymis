<?php

namespace Database\Factories\HR;

use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveApplicationFactory extends Factory
{
    protected $model = LeaveApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'leave_type_id' => LeaveType::factory(),
            'date_from' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'date_to' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'days_applied' => 1,
            'status' => 'pending',
        ];
    }
}
