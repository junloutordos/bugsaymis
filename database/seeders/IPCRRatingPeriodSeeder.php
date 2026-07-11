<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IPCRRatingPeriod;

class IPCRRatingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $today = now();

        foreach ([2024, 2025, 2026, 2027] as $year) {
            $semesters = [
                1 => ['label' => "January 1 to June 30, {$year}",  'start' => "{$year}-01-01", 'end' => "{$year}-06-30"],
                2 => ['label' => "July 1 to December 31, {$year}", 'start' => "{$year}-07-01", 'end' => "{$year}-12-31"],
            ];

            foreach ($semesters as $semester => $def) {
                // Past fiscal years are closed (read-only); current + future stay open
                $status = $year < (int) $today->format('Y')
                    ? IPCRRatingPeriod::STATUS_CLOSED
                    : IPCRRatingPeriod::STATUS_OPEN;

                $isCurrent = $today->between($def['start'], $def['end'].' 23:59:59');

                IPCRRatingPeriod::updateOrCreate(
                    ['label' => $def['label']],
                    [
                        'year'       => $year,
                        'semester'   => $semester,
                        'start_date' => $def['start'],
                        'end_date'   => $def['end'],
                        'status'     => $status,
                        'is_active'  => true,
                        'is_current' => $isCurrent,
                    ]
                );
            }
        }
    }
}
