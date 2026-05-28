<?php

namespace App\Http\Controllers;

use App\Models\IssuanceSeriesSetting;
use App\Models\IssuanceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IssuanceSettingsController extends Controller
{
    public function index(Request $request)
    {
        $year  = $request->integer('year', now()->year);
        $types = IssuanceType::orderBy('label')->get();

        $offsets = IssuanceSeriesSetting::where('year', $year)
            ->pluck('series_start', 'type_code');

        $data = $types->map(fn ($t) => [
            'code'           => $t->code,
            'label'          => $t->label,
            'description'    => $t->description,
            'series_padding' => $t->series_padding,
            'is_active'      => $t->is_active,
            'series_start'   => $offsets[$t->code] ?? 0,
        ]);

        return response()->json(['types' => $data, 'year' => $year]);
    }

    public function storeType(Request $request)
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/', Rule::unique('issuance_types', 'code')],
            'label'          => 'required|string|max:100',
            'description'    => 'nullable|string|max:500',
            'series_padding' => 'required|integer|min:1|max:6',
        ]);

        $type = IssuanceType::create([...$validated, 'is_active' => true]);
        IssuanceType::clearCache();

        return response()->json($type, 201);
    }

    public function updateType(Request $request, string $code)
    {
        $type = IssuanceType::findOrFail($code);

        $validated = $request->validate([
            'label'          => 'sometimes|string|max:100',
            'description'    => 'nullable|string|max:500',
            'series_padding' => 'sometimes|integer|min:1|max:6',
            'is_active'      => 'sometimes|boolean',
        ]);

        if (isset($validated['is_active']) && ! $validated['is_active']) {
            if (DB::table('issuances')->where('type', $code)->exists()) {
                return response()->json(['message' => 'Cannot disable a type that already has issuances.'], 422);
            }
        }

        $type->update($validated);
        IssuanceType::clearCache();

        return response()->json($type);
    }

    public function destroyType(string $code)
    {
        $type = IssuanceType::findOrFail($code);

        if (DB::table('issuances')->where('type', $code)->exists()) {
            return response()->json(['message' => 'Cannot delete a type that already has issuances.'], 422);
        }

        $type->delete();
        IssuanceType::clearCache();

        return response()->json(['ok' => true]);
    }

    public function upsertSeries(Request $request)
    {
        $validated = $request->validate([
            'type_code'    => 'required|exists:issuance_types,code',
            'year'         => 'required|integer|min:2000|max:2100',
            'series_start' => 'required|integer|min:0',
        ]);

        IssuanceSeriesSetting::updateOrCreate(
            ['type_code' => $validated['type_code'], 'year' => $validated['year']],
            ['series_start' => $validated['series_start']],
        );

        return response()->json(['ok' => true]);
    }
}
