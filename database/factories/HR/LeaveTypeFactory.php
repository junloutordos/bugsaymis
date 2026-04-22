<?php

namespace Database\Factories\HR;

use App\Models\HR\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'code'                       => strtoupper(fake()->unique()->lexify('??')),
            'name'                       => fake()->words(2, true),
            'description'                => null,
            'days_per_year'              => null,
            'is_creditable'              => true,
            'is_deductible'              => true,
            'requires_approval'          => true,
            'min_days_notice'            => 0,
            'max_days_per_application'   => null,
            'with_pay'                   => true,
            'applicable_employment_types' => null,
            'is_active'                  => true,
            'sort_order'                 => 0,
        ];
    }
}
