<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IpcrFactory extends Factory
{
    protected $model = Ipcr::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fiscal_period_id' => FiscalPeriod::factory(['cadence' => 'semester']),
            'dpcr_id' => null,
            'status' => Ipcr::STATUS_DRAFT_TARGET,
        ];
    }
}
