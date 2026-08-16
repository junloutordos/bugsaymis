<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\FiscalPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalPeriodFactory extends Factory
{
    protected $model = FiscalPeriod::class;

    public function definition(): array
    {
        return [
            'cadence' => 'quarter',
            'fiscal_year' => 2026,
            'label' => 'Q1 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'parent_period_id' => null,
            'school_year_id' => null,
            'is_current' => false,
        ];
    }
}
