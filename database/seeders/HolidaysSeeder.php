<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HolidaysSeeder extends Seeder
{
    public function run(): void
    {
        // Philippine holidays for 2026 (Proclamation No. TBD — using standard recurring dates)
        $holidays = [
            // Regular Holidays
            ['name' => "New Year's Day",            'holiday_date' => '2026-01-01', 'type' => 'regular',             'is_recurring' => true],
            ['name' => 'Araw ng Kagitingan',         'holiday_date' => '2026-04-09', 'type' => 'regular',             'is_recurring' => true],
            ['name' => 'Labor Day',                  'holiday_date' => '2026-05-01', 'type' => 'regular',             'is_recurring' => true],
            ['name' => 'Independence Day',           'holiday_date' => '2026-06-12', 'type' => 'regular',             'is_recurring' => true],
            ['name' => 'National Heroes Day',        'holiday_date' => '2026-08-31', 'type' => 'regular',             'is_recurring' => false],  // Last Monday of August
            ['name' => 'Bonifacio Day',              'holiday_date' => '2026-11-30', 'type' => 'regular',             'is_recurring' => true],
            ['name' => 'Christmas Day',              'holiday_date' => '2026-12-25', 'type' => 'regular',             'is_recurring' => true],
            ['name' => 'Rizal Day',                  'holiday_date' => '2026-12-30', 'type' => 'regular',             'is_recurring' => true],
            // Maundy Thursday / Good Friday (moveable — 2026 dates)
            ['name' => 'Maundy Thursday',            'holiday_date' => '2026-04-02', 'type' => 'regular',             'is_recurring' => false],
            ['name' => 'Good Friday',                'holiday_date' => '2026-04-03', 'type' => 'regular',             'is_recurring' => false],
            // Eid'l Fitr / Eid'l Adha (moveable — approximate 2026)
            ['name' => "Eid'l Fitr",                 'holiday_date' => '2026-03-20', 'type' => 'regular',             'is_recurring' => false],
            ['name' => "Eid'l Adha",                 'holiday_date' => '2026-05-27', 'type' => 'regular',             'is_recurring' => false],

            // Special Non-Working Holidays
            ['name' => 'Chinese New Year',           'holiday_date' => '2026-02-17', 'type' => 'special_non_working', 'is_recurring' => false],
            ['name' => 'EDSA People Power Anniversary', 'holiday_date' => '2026-02-25', 'type' => 'special_non_working', 'is_recurring' => true],
            ['name' => 'Black Saturday',             'holiday_date' => '2026-04-04', 'type' => 'special_non_working', 'is_recurring' => false],
            ['name' => 'Ninoy Aquino Day',           'holiday_date' => '2026-08-21', 'type' => 'special_non_working', 'is_recurring' => true],
            ['name' => "All Saints' Day",            'holiday_date' => '2026-11-01', 'type' => 'special_non_working', 'is_recurring' => true],
            ['name' => "All Souls' Day",             'holiday_date' => '2026-11-02', 'type' => 'special_non_working', 'is_recurring' => true],
            ['name' => 'Feast of the Immaculate Conception', 'holiday_date' => '2026-12-08', 'type' => 'special_non_working', 'is_recurring' => true],
            ['name' => 'Christmas Eve',              'holiday_date' => '2026-12-24', 'type' => 'special_non_working', 'is_recurring' => true],
            ['name' => "New Year's Eve",             'holiday_date' => '2026-12-31', 'type' => 'special_non_working', 'is_recurring' => true],
        ];

        foreach ($holidays as $h) {
            DB::table('holidays')->updateOrInsert(
                ['holiday_date' => $h['holiday_date'], 'type' => $h['type']],
                array_merge($h, [
                    'description' => null,
                    'is_active'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ])
            );
        }
    }
}
