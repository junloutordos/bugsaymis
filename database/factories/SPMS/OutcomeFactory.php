<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\Outcome;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutcomeFactory extends Factory
{
    protected $model = Outcome::class;

    public function definition(): array
    {
        return [
            'outcome' => $this->faker->sentence(4),
            'sub_outcome' => $this->faker->sentence(3),
            'function_type' => 'core',
            'fiscal_year' => 2026,
        ];
    }
}
