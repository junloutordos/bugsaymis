<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\Outcome;
use App\Models\SPMS\PerformanceIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceIndicatorFactory extends Factory
{
    protected $model = PerformanceIndicator::class;

    public function definition(): array
    {
        return [
            'spms_outcome_id' => Outcome::factory(),
            'description' => $this->faker->sentence(8),
            'target' => '100% compliance',
            'budget' => null,
            'fiscal_year' => 2026,
        ];
    }
}
