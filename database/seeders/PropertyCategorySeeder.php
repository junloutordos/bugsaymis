<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property\PropertyCategory;

class PropertyCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Semi-expendable (ICS) — useful life 1 year
            ['name' => 'Semi-Expendable Furniture and Fixtures',      'account_code' => '10605020', 'type' => 'semi_expendable', 'useful_life_years' => 1,  'residual_rate' => 0.05],
            ['name' => 'Semi-Expendable IT Equipment',                'account_code' => '10605030', 'type' => 'semi_expendable', 'useful_life_years' => 1,  'residual_rate' => 0.05],
            ['name' => 'Semi-Expendable Communication Equipment',     'account_code' => '10605040', 'type' => 'semi_expendable', 'useful_life_years' => 1,  'residual_rate' => 0.05],
            ['name' => 'Semi-Expendable Technical and Scientific Equipment', 'account_code' => '10605060', 'type' => 'semi_expendable', 'useful_life_years' => 1, 'residual_rate' => 0.05],
            ['name' => 'Semi-Expendable Medical Equipment',           'account_code' => '10605080', 'type' => 'semi_expendable', 'useful_life_years' => 1,  'residual_rate' => 0.05],
            ['name' => 'Semi-Expendable Sports Equipment',            'account_code' => '10605090', 'type' => 'semi_expendable', 'useful_life_years' => 1,  'residual_rate' => 0.05],
            ['name' => 'Semi-Expendable Other Machinery and Equipment','account_code' => '10605990', 'type' => 'semi_expendable', 'useful_life_years' => 1, 'residual_rate' => 0.05],

            // PPE (PAR) — varying useful life per GAM
            ['name' => 'Furniture and Fixtures',                      'account_code' => '10604020', 'type' => 'equipment', 'useful_life_years' => 10, 'residual_rate' => 0.05],
            ['name' => 'IT Equipment and Software',                   'account_code' => '10604030', 'type' => 'equipment', 'useful_life_years' => 5,  'residual_rate' => 0.05],
            ['name' => 'Communication Equipment',                     'account_code' => '10604040', 'type' => 'equipment', 'useful_life_years' => 10, 'residual_rate' => 0.05],
            ['name' => 'Technical and Scientific Equipment',          'account_code' => '10604060', 'type' => 'equipment', 'useful_life_years' => 10, 'residual_rate' => 0.05],
            ['name' => 'Medical Equipment',                           'account_code' => '10604080', 'type' => 'equipment', 'useful_life_years' => 10, 'residual_rate' => 0.05],
            ['name' => 'Motor Vehicles',                              'account_code' => '10604010', 'type' => 'equipment', 'useful_life_years' => 10, 'residual_rate' => 0.05],
            ['name' => 'Buildings and Structures',                    'account_code' => '10602020', 'type' => 'equipment', 'useful_life_years' => 30, 'residual_rate' => 0.05],
            ['name' => 'Other Machinery and Equipment',               'account_code' => '10604990', 'type' => 'equipment', 'useful_life_years' => 10, 'residual_rate' => 0.05],
        ];

        foreach ($categories as $data) {
            PropertyCategory::updateOrCreate(['account_code' => $data['account_code']], $data);
        }

        $this->command->info('Property categories seeded: '.count($categories).' records.');
    }
}
