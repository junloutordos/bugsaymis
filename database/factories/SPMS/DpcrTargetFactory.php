<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\Dpcr;
use App\Models\SPMS\DpcrTarget;
use App\Models\SPMS\PerformanceIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

class DpcrTargetFactory extends Factory
{
    protected $model = DpcrTarget::class;

    public function definition(): array
    {
        return [
            'dpcr_id' => Dpcr::factory(),
            'spms_performance_indicator_id' => PerformanceIndicator::factory(),
        ];
    }
}
