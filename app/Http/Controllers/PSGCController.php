<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PSGCController extends Controller
{
    private const TTL = 86400;

    public function regions(): JsonResponse
    {
        $data = Cache::remember('psgc:regions', self::TTL, fn() =>
            DB::table('psgc_regions')->orderBy('name')->get(['code', 'name'])
        );
        return response()->json($data);
    }

    public function provinces(Request $request): JsonResponse
    {
        $regionCode = $request->query('region', '');
        $key = 'psgc:provinces:' . $regionCode;
        $data = Cache::remember($key, self::TTL, fn() =>
            DB::table('psgc_provinces')
                ->when($regionCode, fn($q) => $q->where('region_code', $regionCode))
                ->orderBy('name')
                ->get(['code', 'name', 'type'])
        );
        return response()->json($data);
    }

    public function cities(Request $request): JsonResponse
    {
        $provinceCode = $request->query('province', '');
        abort_if(empty($provinceCode), 400, 'province parameter required');
        $key = 'psgc:cities:' . $provinceCode;
        $data = Cache::remember($key, self::TTL, fn() =>
            DB::table('psgc_cities')
                ->where('province_code', $provinceCode)
                ->orderBy('name')
                ->get(['code', 'name'])
        );
        return response()->json($data);
    }

    public function barangays(Request $request): JsonResponse
    {
        $cityCode = $request->query('city', '');
        abort_if(empty($cityCode), 400, 'city parameter required');
        $key = 'psgc:barangays:' . $cityCode;
        $data = Cache::remember($key, self::TTL, fn() =>
            DB::table('psgc_barangays')
                ->where('city_code', $cityCode)
                ->orderBy('name')
                ->get(['code', 'name'])
        );
        return response()->json($data);
    }
}
