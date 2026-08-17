<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Opcr;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpcrFactory extends Factory
{
    protected $model = Opcr::class;

    public function definition(): array
    {
        return [
            'fiscal_period_id' => FiscalPeriod::factory(['cadence' => 'annual']),
            'ratee_user_id' => User::factory(),
            'status' => Opcr::STATUS_DRAFT,
        ];
    }
}
