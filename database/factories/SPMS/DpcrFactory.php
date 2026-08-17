<?php

namespace Database\Factories\SPMS;

use App\Models\Division;
use App\Models\SPMS\Dpcr;
use App\Models\SPMS\FiscalPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DpcrFactory extends Factory
{
    protected $model = Dpcr::class;

    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'fiscal_period_id' => FiscalPeriod::factory(['cadence' => 'semester']),
            'ratee_user_id' => User::factory(),
            'status' => Dpcr::STATUS_DRAFT,
            'unit_count' => 0,
        ];
    }
}
