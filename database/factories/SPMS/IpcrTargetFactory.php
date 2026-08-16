<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

class IpcrTargetFactory extends Factory
{
    protected $model = IpcrTarget::class;

    public function definition(): array
    {
        return [
            'ipcr_id' => Ipcr::factory(),
            'function_type' => 'core',
            'source_type' => null,
            'source_id' => null,
            'success_indicator' => $this->faker->sentence(6),
            'target' => '100%',
            'rubric_text' => null,
            'weight_pct' => 10,
        ];
    }
}
