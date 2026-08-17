<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\Opcr;
use App\Models\SPMS\OpcrTarget;
use App\Models\SPMS\PerformanceIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpcrTargetFactory extends Factory
{
    protected $model = OpcrTarget::class;

    public function definition(): array
    {
        return [
            'opcr_id' => Opcr::factory(),
            'spms_performance_indicator_id' => PerformanceIndicator::factory(),
        ];
    }
}
