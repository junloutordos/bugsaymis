<?php

namespace App\Http\Controllers\Api;

use App\Events\BiometricPunchRecorded;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\User;
use App\Services\HR\BiometricImportService;
use App\Services\HR\DTRService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricPunchIngestController extends Controller
{
    public function store(Request $request, BiometricImportService $importService, DTRService $dtrService): JsonResponse
    {
        $bridge = BiometricDevice::where('ict_equipment_device_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (! $bridge) {
            abort(403, 'This Atlas Sentinel device is not registered as a biometric bridge.');
        }

        $validated = $request->validate([
            'raw_body' => ['required', 'string', 'max:100000'],
        ]);

        $stats = $importService->ingestApiPunches($validated['raw_body'], $bridge->device_key);

        $bridge->update(['last_relay_at' => now()]);

        $userNames = User::whereIn(
            'id',
            array_values(array_filter(array_column($stats['new_rows'], 'user_id')))
        )->pluck('name', 'id');

        foreach ($stats['new_rows'] as $row) {
            rescue(fn () => event(new BiometricPunchRecorded([
                'user_id'            => $row['user_id'],
                'user_name'          => $row['user_id'] ? ($userNames[$row['user_id']] ?? null) : null,
                'device_employee_id' => $row['device_employee_id'],
                'device_label'       => $bridge->label,
                'log_type'           => $row['log_type'],
                'log_datetime'       => $row['log_datetime'],
                'is_resolved'        => $row['is_resolved'],
            ])));
        }

        // Dedupe to one DTR::generate() call per (user_id, date) pair, no
        // matter how many punches that user had in this request — a backlog
        // replay can otherwise trigger the same multi-table computation
        // dozens of times for a single employee/day.
        $affectedDates = [];

        foreach ($stats['new_rows'] as $row) {
            if (! $row['user_id']) {
                continue;
            }

            $date = Carbon::parse($row['log_datetime'])->toDateString();
            $affectedDates[$row['user_id'] . '|' . $date] = [
                'user_id' => (int) $row['user_id'],
                'date'    => $date,
            ];
        }

        foreach ($affectedDates as $pair) {
            $dtrService->generate($pair['user_id'], $pair['date'], $pair['date']);
        }

        return response()->json(['status' => 'ok'] + collect($stats)->except('new_rows')->all());
    }
}
