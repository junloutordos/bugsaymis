<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PSGCSeeder extends Seeder
{
    private const BASE = 'https://psgc.gitlab.io/api';

    public function run(): void
    {
        $this->command->info('Fetching PSGC data from psgc.gitlab.io...');

        // Regions
        $regions = Http::timeout(30)->get(self::BASE . '/regions/')->json();
        DB::table('psgc_regions')->truncate();
        DB::table('psgc_regions')->insert(
            collect($regions)->map(fn($r) => ['code' => $r['code'], 'name' => $r['name']])->toArray()
        );
        $this->command->info('Regions inserted: ' . count($regions));

        // Provinces
        $provinces = Http::timeout(30)->get(self::BASE . '/provinces/')->json();
        DB::table('psgc_provinces')->truncate();
        DB::table('psgc_provinces')->insert(
            collect($provinces)->map(fn($p) => [
                'code'        => $p['code'],
                'name'        => $p['name'],
                'region_code' => $p['regionCode'] ?? '',
                'type'        => 'province',
            ])->toArray()
        );
        $this->command->info('Provinces inserted: ' . count($provinces));

        // Cities + Municipalities — HUCs (no province) also go into psgc_provinces as type='city'
        $this->command->info('Fetching cities/municipalities...');
        $cities = Http::timeout(60)->get(self::BASE . '/cities-municipalities/')->json();
        DB::table('psgc_cities')->truncate();

        $hucRows  = [];
        $cityRows = [];
        foreach ($cities as $c) {
            $provinceCode = $c['provinceCode'] ?? '';
            $regionCode   = $c['regionCode'] ?? '';
            if (empty($provinceCode)) {
                $hucRows[] = [
                    'code'        => $c['code'],
                    'name'        => $c['name'],
                    'region_code' => $regionCode,
                    'type'        => 'city',
                ];
            }
            $cityRows[] = [
                'code'          => $c['code'],
                'name'          => $c['name'],
                'province_code' => empty($provinceCode) ? null : $provinceCode,
                'region_code'   => $regionCode,
            ];
        }
        foreach (array_chunk($cityRows, 500) as $chunk) {
            DB::table('psgc_cities')->insert($chunk);
        }
        if (!empty($hucRows)) {
            DB::table('psgc_provinces')->insert($hucRows);
        }
        $this->command->info('Cities/Municipalities inserted: ' . count($cities) . ' (' . count($hucRows) . ' HUC/ICC added to provinces)');

        // Barangays — largest dataset, insert in chunks of 1000
        $this->command->info('Fetching barangays (this may take a while)...');
        $barangays = Http::timeout(120)->get(self::BASE . '/barangays/')->json();
        DB::table('psgc_barangays')->truncate();

        $barangayRows = collect($barangays)->map(function ($b) {
            // API returns false (not null) for empty codes — use ?: not ?? to coalesce falsy values
            $cityCode = $b['cityCode'] ?: ($b['municipalityCode'] ?: '');
            if (empty($cityCode)) return null;
            return ['code' => $b['code'], 'name' => $b['name'], 'city_code' => $cityCode];
        })->filter()->values()->toArray();

        foreach (array_chunk($barangayRows, 1000) as $chunk) {
            DB::table('psgc_barangays')->insert($chunk);
        }
        $this->command->info('Barangays inserted: ' . count($barangayRows));
        $this->command->info('PSGC seeding complete.');
    }
}
