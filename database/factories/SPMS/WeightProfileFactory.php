<?php

namespace Database\Factories\SPMS;

use App\Models\SPMS\WeightProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class WeightProfileFactory extends Factory
{
    protected $model = WeightProfile::class;

    public function definition(): array
    {
        return [
            'level' => 'ipcr',
            'division_id' => null,
            'fiscal_year' => 2026,
            'strategic_pct' => 30,
            'core_pct' => 50,
            'support_pct' => 20,
            'core_subweights' => null,
        ];
    }
}
