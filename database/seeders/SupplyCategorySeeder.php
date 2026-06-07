<?php

namespace Database\Seeders;

use App\Models\Supply\SupplyCategory;
use Illuminate\Database\Seeder;

class SupplyCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Office Supplies — consumable, UACS 5-02-03-010
            ['name' => 'Office Supplies',              'account_code' => '5-02-03-010', 'type' => 'consumable',      'description' => 'Bond paper, pens, folders, staples, and general office consumables'],
            ['name' => 'Cleaning Supplies',            'account_code' => '5-02-03-010', 'type' => 'consumable',      'description' => 'Cleaning agents, mops, brooms, and janitorial materials'],
            ['name' => 'Other Supplies & Materials',   'account_code' => '5-02-03-990', 'type' => 'consumable',      'description' => 'Miscellaneous supplies not elsewhere classified'],

            // Semi-Expendable — items < ₱15,000 useful life > 1 year, per GAM
            ['name' => 'Semi-Expendable Furniture',           'account_code' => '5-02-03-070', 'type' => 'semi_expendable', 'description' => 'Chairs, tables, shelves costing below the capitalization threshold'],
            ['name' => 'Semi-Expendable Office Equipment',    'account_code' => '5-02-03-080', 'type' => 'semi_expendable', 'description' => 'Calculators, clocks, small office devices below capitalization threshold'],
            ['name' => 'Semi-Expendable ICT Equipment',       'account_code' => '5-02-03-090', 'type' => 'semi_expendable', 'description' => 'Flash drives, keyboards, mice, headsets below capitalization threshold'],
            ['name' => 'Semi-Expendable Technical Equipment', 'account_code' => '5-02-03-100', 'type' => 'semi_expendable', 'description' => 'Scientific instruments and tools below capitalization threshold'],
            ['name' => 'Semi-Expendable Other Equipment',     'account_code' => '5-02-03-990', 'type' => 'semi_expendable', 'description' => 'Other semi-expendable items not elsewhere classified'],

            // Equipment / PPE — items ≥ ₱15,000
            ['name' => 'Furniture & Fixtures',         'account_code' => '1-07-07-010', 'type' => 'equipment', 'description' => 'Office furniture and fixtures with cost ≥ ₱15,000'],
            ['name' => 'Office Equipment',             'account_code' => '1-07-05-020', 'type' => 'equipment', 'description' => 'Printers, copiers, projectors and other office equipment ≥ ₱15,000'],
            ['name' => 'ICT Equipment',                'account_code' => '1-07-05-030', 'type' => 'equipment', 'description' => 'Computers, laptops, servers, networking equipment ≥ ₱15,000'],
            ['name' => 'Technical & Scientific Equipment', 'account_code' => '1-07-05-040', 'type' => 'equipment', 'description' => 'Laboratory and science equipment ≥ ₱15,000'],
            ['name' => 'Communication Equipment',      'account_code' => '1-07-05-050', 'type' => 'equipment', 'description' => 'Radio sets, PABX, and communication devices ≥ ₱15,000'],
            ['name' => 'Motor Vehicles',               'account_code' => '1-07-06-010', 'type' => 'equipment', 'description' => 'School vehicles and transportation equipment'],
            ['name' => 'Other Property, Plant & Equipment', 'account_code' => '1-07-99-990', 'type' => 'equipment', 'description' => 'Tangible PPE not elsewhere classified'],
        ];

        foreach ($categories as $cat) {
            SupplyCategory::updateOrCreate(
                ['name' => $cat['name']],
                $cat + ['is_active' => true]
            );
        }

        $this->command->info('Supply categories seeded: ' . count($categories) . ' categories.');
    }
}
