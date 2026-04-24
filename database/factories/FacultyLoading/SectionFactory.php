<?php

namespace Database\Factories\FacultyLoading;

use App\Models\FacultyLoading\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'levelid'     => $this->faker->randomElement([7, 8, 9, 10, 11, 12]),
            'sectionname' => $this->faker->word(),
            'is_active'   => true,
            'syid'        => null,
        ];
    }
}
