<?php

namespace Database\Seeders;

use App\Models\FacultyLoading\SchoolDayConfig;
use Illuminate\Database\Seeder;

/**
 * Seeds the school_day_configs table with PSHS-CRC's weekly schedule template.
 *
 * Monday    : Flag Ceremony 7:30–8:00, Adviser Time 8:00–8:30
 *             Classes start 8:30, end 4:30 PM  (lunch 12:00–1:00)
 * Tuesday   : Classes 7:00 AM – 4:30 PM        (lunch 12:00–1:00)
 * Wednesday : Classes 7:30 AM – 12:00 NN only  (Club Time 1:00–4:30 PM)
 * Thursday  : Classes 7:00 AM – 4:30 PM        (lunch 12:00–1:00)
 * Friday    : Classes 7:30 AM – 12:00 NN only
 */
class SchoolDayConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'day_of_week'     => 'Monday',
                'classes_start'   => '08:30:00',
                'classes_end'     => '16:30:00',
                'blocked_periods' => [
                    ['start' => '07:30', 'end' => '08:00', 'label' => 'Flag Ceremony'],
                    ['start' => '08:00', 'end' => '08:30', 'label' => 'Adviser Time'],
                    ['start' => '12:00', 'end' => '13:00', 'label' => 'Lunch Break'],
                ],
                'notes'     => 'Flag Ceremony 7:30–8:00 AM, Adviser Time 8:00–8:30 AM',
                'is_active' => true,
            ],
            [
                'day_of_week'     => 'Tuesday',
                'classes_start'   => '07:00:00',
                'classes_end'     => '16:30:00',
                'blocked_periods' => [
                    ['start' => '12:00', 'end' => '13:00', 'label' => 'Lunch Break'],
                ],
                'notes'     => null,
                'is_active' => true,
            ],
            [
                'day_of_week'     => 'Wednesday',
                'classes_start'   => '07:30:00',
                'classes_end'     => '12:00:00',
                'blocked_periods' => [],
                'notes'     => 'Club Time 1:00–4:30 PM — no classes in the afternoon',
                'is_active' => true,
            ],
            [
                'day_of_week'     => 'Thursday',
                'classes_start'   => '07:00:00',
                'classes_end'     => '16:30:00',
                'blocked_periods' => [
                    ['start' => '12:00', 'end' => '13:00', 'label' => 'Lunch Break'],
                ],
                'notes'     => null,
                'is_active' => true,
            ],
            [
                'day_of_week'     => 'Friday',
                'classes_start'   => '07:30:00',
                'classes_end'     => '12:00:00',
                'blocked_periods' => [],
                'notes'     => 'No afternoon classes',
                'is_active' => true,
            ],
        ];

        foreach ($configs as $config) {
            SchoolDayConfig::updateOrCreate(
                ['day_of_week' => $config['day_of_week']],
                $config
            );
        }
    }
}
