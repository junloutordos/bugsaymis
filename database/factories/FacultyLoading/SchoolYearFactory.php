<?php

namespace Database\Factories\FacultyLoading;

use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolYearFactory extends Factory
{
    protected $model = SchoolYear::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;
        $year = 2020 + $seq;

        return [
            'name'       => "{$year}-" . ($year + 1),
            'start_date' => "{$year}-06-01",
            'end_date'   => ($year + 1) . '-03-31',
            'is_current' => false,
            'status'     => 'active',
        ];
    }
}
