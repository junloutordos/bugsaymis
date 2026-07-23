<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\UploadBiometricFileRequest;
use App\Models\HR\BiometricLog;
use App\Models\User;
use App\Services\HR\BiometricImportService;
use App\Services\HR\DTRService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BiometricLogController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->hasAnyPermission(['hr.biometric.manage', 'hr.biometric.monitor'])) {
            abort(403);
        }

        $query = BiometricLog::with('user')
            ->when($request->resolved === 'true',  fn ($q) => $q->where('is_resolved', true))
            ->when($request->resolved === 'false', fn ($q) => $q->where('is_resolved', false))
            ->when($request->batch,   fn ($q) => $q->where('import_batch', $request->batch))
            ->when($request->search,  fn ($q) => $q->where('device_employee_id', 'like', '%' . $request->search . '%'))
            ->orderByDesc('log_datetime');

        $stats = [
            'total'      => BiometricLog::count(),
            'resolved'   => BiometricLog::where('is_resolved', true)->count(),
            'unresolved' => BiometricLog::where('is_resolved', false)->count(),
            'duplicates' => BiometricLog::where('is_duplicate', true)->count(),
        ];

        return Inertia::render('HR/Biometric/Index', [
            'logs'      => $query->paginate(50)->withQueryString(),
            'stats'     => $stats,
            'users'     => User::employees()->where('status', 'active')
                ->select('id', 'name', 'badge_id')
                ->orderBy('name')
                ->get(),
            'filters'   => $request->only(['resolved', 'batch', 'search']),
            'canManage' => auth()->user()->hasPermission('hr.biometric.manage'),
        ]);
    }

    public function upload(UploadBiometricFileRequest $request, BiometricImportService $importService, DTRService $dtrService)
    {
        $totals  = ['inserted' => 0, 'resolved' => 0, 'unresolved' => 0, 'duplicates' => 0, 'skipped' => 0];
        $batches = [];

        foreach ($request->file('files') as $file) {
            $path  = $file->store('hr/biometric_imports', 'local');
            $batch = (string) Str::uuid();

            $result = $importService->parse(Storage::disk('local')->path($path), $batch, $request->device_id);

            foreach ($totals as $key => $_) {
                $totals[$key] += $result[$key] ?? 0;
            }

            $batches[] = $batch;
        }

        // Auto-generate DTR for all resolved users whose logs were just imported
        if (! empty($batches) && $totals['resolved'] > 0) {
            $affected = BiometricLog::whereIn('import_batch', $batches)
                ->where('is_resolved', true)
                ->selectRaw('user_id, MIN(DATE(log_datetime)) as date_from, MAX(DATE(log_datetime)) as date_to')
                ->groupBy('user_id')
                ->get();

            foreach ($affected as $row) {
                $dtrService->generate($row->user_id, $row->date_from, $row->date_to);
            }
        }

        $msg = "Import complete — {$totals['inserted']} new record(s) inserted "
             . "({$totals['resolved']} resolved, {$totals['unresolved']} unresolved). "
             . "{$totals['duplicates']} duplicate(s) skipped.";

        return back()->with('success', $msg);
    }

    public function resolve(Request $request, BiometricLog $log, DTRService $dtrService)
    {
        $this->authorize('hr.biometric.manage');

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Stamp badge_id on the user so future imports auto-resolve
        User::where('id', $request->user_id)
            ->whereNull('badge_id')
            ->update(['badge_id' => $log->device_employee_id]);

        // Resolve all unresolved logs with the same device_employee_id
        $resolved = BiometricLog::where('device_employee_id', $log->device_employee_id)
            ->where('is_resolved', false)
            ->update([
                'user_id'     => $request->user_id,
                'is_resolved' => true,
            ]);

        // Generate DTR for the newly resolved logs
        $range = BiometricLog::where('device_employee_id', $log->device_employee_id)
            ->selectRaw('MIN(DATE(log_datetime)) as date_from, MAX(DATE(log_datetime)) as date_to')
            ->first();

        if ($range->date_from) {
            $dtrService->generate($request->user_id, $range->date_from, $range->date_to);
        }

        return back()->with('success', "Resolved {$resolved} log(s) and generated DTR for device ID {$log->device_employee_id}.");
    }
}
