<?php

namespace App\Http\Controllers;

use App\Models\IctBackupFile;
use App\Models\IctBackupFolder;
use App\Models\IctBackupRun;
use App\Models\IctBackupSchedule;
use App\Models\IctEquipmentDevice;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * MIS-facing management of Atlas Sentinel document backups: which enrolled
 * devices are on the schedule, how each device's last run went, and manual
 * "back up now" dispatch. The agent-facing half lives in
 * Api\AtlasSentinelController.
 */
class AtlasSentinelBackupController extends Controller
{
    public function index()
    {
        $devices = IctEquipmentDevice::with('equipment:id,description,category,serial_no')
            ->orderBy('hostname')
            ->get();

        $schedules = IctBackupSchedule::get()->keyBy('device_id');

        $latestRuns = IctBackupRun::whereIn('id', IctBackupRun::selectRaw('MAX(id)')->groupBy('device_id'))
            ->get()
            ->keyBy('device_id');

        $stats = IctBackupFile::selectRaw('device_id, COUNT(*) as files, COALESCE(SUM(size_bytes), 0) as bytes')
            ->groupBy('device_id')
            ->get()
            ->keyBy('device_id');

        $rootFolders = IctBackupFolder::where('path_hash', sha1(''))
            ->pluck('drive_folder_id', 'device_id');

        return Inertia::render('ITJobRequests/DeviceBackups', [
            'devices' => $devices->map(function ($device) use ($schedules, $latestRuns, $stats, $rootFolders) {
                $schedule = $schedules[$device->id] ?? null;
                $run = $latestRuns[$device->id] ?? null;
                $stat = $stats[$device->id] ?? null;

                return [
                    'id' => $device->id,
                    'hostname' => $device->hostname,
                    'equipment_id' => $device->equipment_id,
                    'equipment_label' => $device->equipment?->description,
                    'last_checkin_at' => $device->last_checkin_at,
                    'schedule' => $schedule ? [
                        'id' => $schedule->id,
                        'enabled' => $schedule->enabled,
                        'frequency' => $schedule->frequency,
                        'weekly_day' => $schedule->weekly_day,
                        'preferred_hour' => $schedule->preferred_hour,
                        'include_downloads' => $schedule->include_downloads,
                        'max_file_mb' => $schedule->max_file_mb,
                        'run_requested_at' => $schedule->run_requested_at,
                    ] : null,
                    'last_run' => $run ? [
                        'status' => $run->status,
                        'dispatched_at' => $run->dispatched_at,
                        'finished_at' => $run->finished_at,
                        'files_scanned' => $run->files_scanned,
                        'files_uploaded' => $run->files_uploaded,
                        'files_skipped' => $run->files_skipped,
                        'bytes_uploaded' => $run->bytes_uploaded,
                        'errors' => $run->errors,
                    ] : null,
                    'backed_up_files' => (int) ($stat->files ?? 0),
                    'backed_up_bytes' => (int) ($stat->bytes ?? 0),
                    'drive_folder_url' => isset($rootFolders[$device->id])
                        ? "https://drive.google.com/drive/folders/{$rootFolders[$device->id]}"
                        : null,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'exists:ict_equipment_devices,id', 'unique:ict_backup_schedules,device_id'],
            'frequency' => ['required', 'in:daily,weekly'],
            'weekly_day' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'],
            'preferred_hour' => ['required', 'integer', 'between:0,23'],
            'include_downloads' => ['boolean'],
            'max_file_mb' => ['required', 'integer', 'between:1,2048'],
        ]);

        IctBackupSchedule::create($validated + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Device added to the backup schedule.');
    }

    public function update(Request $request, IctBackupSchedule $schedule)
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'frequency' => ['required', 'in:daily,weekly'],
            'weekly_day' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'],
            'preferred_hour' => ['required', 'integer', 'between:0,23'],
            'include_downloads' => ['boolean'],
            'max_file_mb' => ['required', 'integer', 'between:1,2048'],
        ]);

        $schedule->update($validated);

        return back()->with('success', 'Backup schedule updated.');
    }

    public function destroy(IctBackupSchedule $schedule)
    {
        $schedule->delete();

        return back()->with('success', 'Device removed from the backup schedule.');
    }

    /**
     * Queue an immediate run — picked up on the device's next check-in
     * (≤ 20 minutes). Requires an enabled schedule; backups never run on
     * devices that aren't on the schedule.
     */
    public function runNow(IctBackupSchedule $schedule)
    {
        if (! $schedule->enabled) {
            return back()->with('error', 'Enable the schedule first — backups only run for scheduled devices.');
        }

        $schedule->update(['run_requested_at' => now()]);

        return back()->with('success', 'Backup queued — it will start on the device\'s next check-in (within ~20 minutes).');
    }
}
