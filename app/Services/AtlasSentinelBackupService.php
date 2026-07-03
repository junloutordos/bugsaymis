<?php

namespace App\Services;

use App\Models\IctBackupFile;
use App\Models\IctBackupFolder;
use App\Models\IctBackupRun;
use App\Models\IctBackupSchedule;
use App\Models\IctEquipmentDevice;

/**
 * Server-side brain of Atlas Sentinel document backups. Decides WHEN a
 * device backs up (schedule gate — no ict_backup_schedules row, no work),
 * WHAT it uploads (manifest diff by content hash), and WHERE it lands
 * (per-device Drive folder tree, IDs cached in ict_backup_folders).
 *
 * File bytes never pass through this server: the agent asks for a Drive
 * resumable-session URI per file and PUTs straight to googleapis.com.
 */
class AtlasSentinelBackupService
{
    /** Document types collected from user profiles, sent to the agent per run. */
    public const EXTENSIONS = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'pdf'];

    /** A run stuck in dispatched/running longer than this is marked failed. */
    private const STALE_RUN_HOURS = 12;

    /** Top-level Drive folder that holds one subfolder per device. */
    private const PARENT_FOLDER_NAME = 'Atlas Sentinel Device Backups';

    public function __construct(private GoogleDriveService $drive)
    {
    }

    /**
     * Called on every agent checkin. Returns the backup instruction to embed
     * in the checkin response, or null when there's nothing to do.
     */
    public function pendingBackup(IctEquipmentDevice $device): ?array
    {
        $schedule = IctBackupSchedule::where('device_id', $device->id)
            ->where('enabled', true)
            ->first();

        if (! $schedule) {
            return null;
        }

        $this->failStaleRuns($device);

        $active = IctBackupRun::where('device_id', $device->id)
            ->whereIn('status', ['dispatched', 'running'])
            ->latest('id')
            ->first();

        if ($active) {
            // Re-deliver until the agent starts it (a checkin response can be
            // lost); once running, the agent owns it — don't double-dispatch.
            return $active->status === 'dispatched' ? $this->instructionFor($active) : null;
        }

        if ($schedule->run_requested_at || $this->isDue($schedule)) {
            return $this->instructionFor($this->dispatchRun($schedule));
        }

        return null;
    }

    private function isDue(IctBackupSchedule $schedule): bool
    {
        $now = now(); // app timezone is Asia/Manila

        if ($now->hour < $schedule->preferred_hour) {
            return false;
        }

        if ($schedule->frequency === 'weekly' && $now->dayOfWeek !== (int) $schedule->weekly_day) {
            return false;
        }

        // At most one scheduled dispatch per calendar day — a failed run
        // retries on the next cycle, not in a same-day loop.
        return ! $schedule->last_dispatched_at || ! $schedule->last_dispatched_at->isSameDay($now);
    }

    private function dispatchRun(IctBackupSchedule $schedule): IctBackupRun
    {
        $run = IctBackupRun::create([
            'device_id' => $schedule->device_id,
            'schedule_id' => $schedule->id,
            'status' => 'dispatched',
            'dispatched_at' => now(),
            'config' => [
                'extensions' => self::EXTENSIONS,
                'include_downloads' => $schedule->include_downloads,
                'max_file_mb' => $schedule->max_file_mb,
            ],
        ]);

        $schedule->update(['last_dispatched_at' => now(), 'run_requested_at' => null]);

        return $run;
    }

    private function instructionFor(IctBackupRun $run): array
    {
        return array_merge(['run_id' => $run->id], $run->config ?? []);
    }

    private function failStaleRuns(IctEquipmentDevice $device): void
    {
        IctBackupRun::where('device_id', $device->id)
            ->whereIn('status', ['dispatched', 'running'])
            ->where('dispatched_at', '<', now()->subHours(self::STALE_RUN_HOURS))
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'errors' => json_encode(['Run went stale — the agent never reported completion.']),
            ]);
    }

    /**
     * Diff a manifest batch against what's already backed up. Returns the
     * relative paths the agent still needs to upload (new or content changed).
     * First batch also flips the run to running.
     */
    public function recordManifest(IctBackupRun $run, array $files): array
    {
        if ($run->status === 'dispatched') {
            $run->update(['status' => 'running', 'started_at' => now()]);
        }

        $run->increment('files_scanned', count($files));

        $hashes = array_map(fn ($f) => sha1($f['relative_path']), $files);

        $known = IctBackupFile::where('device_id', $run->device_id)
            ->whereIn('path_hash', $hashes)
            ->pluck('content_sha256', 'path_hash');

        $needed = [];
        foreach ($files as $file) {
            $existing = $known[sha1($file['relative_path'])] ?? null;
            if ($existing === null || ! hash_equals($existing, strtolower($file['sha256']))) {
                $needed[] = $file['relative_path'];
            }
        }

        return $needed;
    }

    /**
     * Issue a Drive resumable-session URI for one file. Existing files get an
     * update session (Drive keeps the old content as a revision); files whose
     * Drive copy was deleted out-of-band fall back to a fresh create.
     */
    public function createUploadSession(IctBackupRun $run, array $file): string
    {
        $segments = $this->sanitizePath($file['relative_path']);
        $fileName = array_pop($segments);
        $mimeType = $file['mime_type'] ?: 'application/octet-stream';
        $sizeBytes = (int) $file['size_bytes'];

        $existing = IctBackupFile::where('device_id', $run->device_id)
            ->where('path_hash', sha1($file['relative_path']))
            ->first();

        if ($existing?->drive_file_id) {
            try {
                return $this->drive->createResumableUpdateSession($existing->drive_file_id, $mimeType, $sizeBytes);
            } catch (DriveFileGoneException) {
                $existing->update(['drive_file_id' => null]);
            }
        }

        $folderId = $this->ensureFolderForPath($run->device, $segments);

        return $this->drive->createResumableSession($fileName, $mimeType, $sizeBytes, $folderId);
    }

    /**
     * The agent finished PUTting one file to Drive — record its new state so
     * future manifest diffs skip it, and bump the run counters.
     */
    public function recordFileUploaded(IctBackupRun $run, array $file): void
    {
        // Sanity-gate the reported Drive file ID's shape; the agent got it
        // straight from Google's final upload response.
        if (! preg_match('/^[a-zA-Z0-9_-]{10,128}$/', $file['drive_file_id'])) {
            abort(422, 'Malformed Drive file id.');
        }

        IctBackupFile::updateOrCreate(
            [
                'device_id' => $run->device_id,
                'path_hash' => sha1($file['relative_path']),
            ],
            [
                'relative_path' => $file['relative_path'],
                'size_bytes' => (int) $file['size_bytes'],
                'content_sha256' => strtolower($file['sha256']),
                'file_mtime' => $file['mtime'] ?? null,
                'drive_file_id' => $file['drive_file_id'],
                'last_backed_up_at' => now(),
            ]
        );

        $run->increment('files_uploaded');
        $run->increment('bytes_uploaded', (int) $file['size_bytes']);
    }

    public function completeRun(IctBackupRun $run, array $stats): void
    {
        $run->update([
            'status' => $stats['status'],
            'files_scanned' => $stats['files_scanned'] ?? $run->files_scanned,
            'files_skipped' => $stats['files_skipped'] ?? null,
            'errors' => array_slice($stats['errors'] ?? [], 0, 100),
            'finished_at' => now(),
        ]);
    }

    /**
     * Resolve (creating as needed) the Drive folder for a directory path,
     * walking segment by segment with every level cached in
     * ict_backup_folders so steady-state backups make zero Drive lookups.
     */
    private function ensureFolderForPath(IctEquipmentDevice $device, array $segments): string
    {
        $folderId = $this->deviceRootFolder($device);

        $path = '';
        foreach ($segments as $segment) {
            $path = $path === '' ? $segment : "{$path}/{$segment}";

            $cached = IctBackupFolder::where('device_id', $device->id)
                ->where('path_hash', sha1($path))
                ->first();

            if ($cached) {
                $folderId = $cached->drive_folder_id;
                continue;
            }

            $folderId = $this->drive->ensureFolder($segment, $folderId);

            IctBackupFolder::create([
                'device_id' => $device->id,
                'path_hash' => sha1($path),
                'path' => $path,
                'drive_folder_id' => $folderId,
            ]);
        }

        return $folderId;
    }

    /**
     * "{PARENT_FOLDER_NAME}/{equipment_id} — {hostname}" under the configured
     * Drive folder. Cached with path '' per device, so the shared parent is
     * only resolved on each device's very first backed-up file.
     */
    private function deviceRootFolder(IctEquipmentDevice $device): string
    {
        $cached = IctBackupFolder::where('device_id', $device->id)
            ->where('path_hash', sha1(''))
            ->first();

        if ($cached) {
            return $cached->drive_folder_id;
        }

        $configuredParent = config('services.google_drive.ict_backup_folder_id');
        if (! $configuredParent) {
            throw new \RuntimeException('No Google Drive folder configured for device backups.');
        }

        $parentId = $this->drive->ensureFolder(self::PARENT_FOLDER_NAME, $configuredParent);

        $label = trim((string) ($device->hostname ?: "device-{$device->id}"));
        $rootId = $this->drive->ensureFolder("{$device->equipment_id} — {$label}", $parentId);

        IctBackupFolder::create([
            'device_id' => $device->id,
            'path_hash' => sha1(''),
            'path' => '',
            'drive_folder_id' => $rootId,
        ]);

        return $rootId;
    }

    /**
     * Split a manifest path into safe segments. The agent normalizes to
     * forward slashes; anything traversal-shaped or Windows-absolute is
     * rejected outright — these paths name Drive folders, nothing more,
     * but garbage in the tree is still garbage.
     */
    private function sanitizePath(string $relativePath): array
    {
        $segments = array_values(array_filter(explode('/', $relativePath), fn ($s) => $s !== ''));

        if (count($segments) === 0) {
            abort(422, 'Empty backup path.');
        }

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..' || str_contains($segment, '\\') || str_contains($segment, ':')) {
                abort(422, 'Unsafe backup path.');
            }
        }

        return $segments;
    }
}
