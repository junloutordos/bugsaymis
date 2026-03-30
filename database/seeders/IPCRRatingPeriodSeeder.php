<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IPCRRatingPeriod;

class IPCRRatingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $periods = [
            ['label' => 'January 1 to June 30, 2024',      'year' => 2024],
            ['label' => 'July 1 to December 31, 2024',     'year' => 2024],
            ['label' => 'January 1 to June 30, 2025',      'year' => 2025],
            ['label' => 'July 1 to December 31, 2025',     'year' => 2025],
            ['label' => 'January 1 to June 30, 2026',      'year' => 2026],
            ['label' => 'July 1 to December 31, 2026',     'year' => 2026],
            ['label' => 'January 1 to June 30, 2027',      'year' => 2027],
            ['label' => 'July 1 to December 31, 2027',     'year' => 2027],
        ];

        foreach ($periods as $period) {
            IPCRRatingPeriod::firstOrCreate(
                ['label' => $period['label']],
                ['year' => $period['year'], 'is_active' => true]
            );
        }
    }
}
