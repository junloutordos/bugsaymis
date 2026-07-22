<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pds_personal_info', function (Blueprint $table) {
            $table->string('residential_region', 100)->nullable()->after('residential_province');
            $table->string('permanent_region', 100)->nullable()->after('permanent_province');
        });

        if (! Schema::hasTable('psgc_regions') || ! Schema::hasTable('psgc_provinces') || ! Schema::hasTable('psgc_cities')) {
            return;
        }

        $regionNames = DB::table('psgc_regions')->pluck('name', 'code');

        $locationRegions = collect()
            ->merge(DB::table('psgc_provinces')->get(['name', 'region_code']))
            ->merge(DB::table('psgc_cities')->get(['name', 'region_code']))
            ->groupBy(fn ($location) => mb_strtolower(trim($location->name)))
            ->map(function ($locations) use ($regionNames) {
                $regions = $locations->pluck('region_code')->unique()->values();

                return $regions->count() === 1 ? $regionNames->get($regions->first()) : null;
            })
            ->filter();

        $resolveRegion = static function (?string $province, ?string $city) use ($locationRegions): ?string {
            foreach ([$province, $city] as $location) {
                $key = mb_strtolower(trim((string) $location));
                if ($key !== '' && $locationRegions->has($key)) {
                    return $locationRegions->get($key);
                }
            }

            return null;
        };

        DB::table('pds_personal_info')
            ->select(['id', 'residential_province', 'residential_city', 'permanent_province', 'permanent_city'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($resolveRegion) {
                foreach ($rows as $row) {
                    $updates = array_filter([
                        'residential_region' => $resolveRegion($row->residential_province, $row->residential_city),
                        'permanent_region' => $resolveRegion($row->permanent_province, $row->permanent_city),
                    ], fn ($value) => $value !== null);

                    if ($updates !== []) {
                        DB::table('pds_personal_info')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('pds_personal_info', function (Blueprint $table) {
            $table->dropColumn(['residential_region', 'permanent_region']);
        });
    }
};
