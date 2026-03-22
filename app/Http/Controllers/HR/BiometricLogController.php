<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\UploadBiometricFileRequest;
use App\Jobs\HR\ProcessBiometricImport;
use App\Models\HR\BiometricLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BiometricLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('hr.biometric.manage');

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
            'logs'    => $query->paginate(50)->withQueryString(),
            'stats'   => $stats,
            'users'   => User::where('status', 'active')
                ->select('id', 'name', 'badge_id')
                ->orderBy('name')
                ->get(),
            'filters' => $request->only(['resolved', 'batch', 'search']),
        ]);
    }

    public function upload(UploadBiometricFileRequest $request)
    {
        $count = 0;

        foreach ($request->file('files') as $file) {
            $path  = $file->store('hr/biometric_imports', 'local');
            $batch = (string) Str::uuid();

            ProcessBiometricImport::dispatch($path, $batch, $request->device_id);
            $count++;
        }

        return back()->with('success', $count . ' file(s) queued for processing. Logs will appear shortly.');
    }

    public function resolve(Request $request, BiometricLog $log)
    {
        $this->authorize('hr.biometric.manage');

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Resolve all unresolved logs with the same device_employee_id
        BiometricLog::where('device_employee_id', $log->device_employee_id)
            ->where('is_resolved', false)
            ->update([
                'user_id'     => $request->user_id,
                'is_resolved' => true,
            ]);

        $resolved = BiometricLog::where('device_employee_id', $log->device_employee_id)->count();

        return back()->with('success', "Resolved {$resolved} log(s) for device ID {$log->device_employee_id}.");
    }
}
