<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\User;
use App\Services\DigitalSignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillNormalizeSignatures extends Command
{
    protected $signature = 'backfill:normalize-signatures {--dry-run : Preview changes without writing to storage}';

    protected $description = 'Crop and normalize all existing stored signature images (users.electronic_signature, '
        . 'divisions.signature_path) to a consistent size, so they render at the same visible size across all print/PDF forms';

    private const NORMALIZED_WIDTH = 400;
    private const NORMALIZED_HEIGHT = 150;

    public function handle(DigitalSignatureService $svc): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode — no files will be modified.');
        }

        $stats = ['processed' => 0, 'skipped_already_normalized' => 0, 'skipped_missing' => 0, 'skipped_blank_or_error' => 0, 'failed' => 0];

        $this->info('── Users (electronic_signature) ──');
        User::whereNotNull('electronic_signature')
            ->where('electronic_signature', '!=', '0')
            ->select(['id', 'electronic_signature'])
            ->chunkById(100, function ($users) use ($svc, $dryRun, &$stats) {
                foreach ($users as $user) {
                    $this->processPath(
                        $svc,
                        $user->electronic_signature,
                        "user #{$user->id}",
                        $dryRun,
                        $stats
                    );
                }
            });

        $this->info('── Divisions (signature_path) ──');
        Division::whereNotNull('signature_path')
            ->where('signature_path', '!=', '0')
            ->select(['id', 'division_name', 'signature_path'])
            ->each(function (Division $division) use ($svc, $dryRun, &$stats) {
                $this->processPath(
                    $svc,
                    $division->signature_path,
                    "division #{$division->id} ({$division->division_name})",
                    $dryRun,
                    $stats
                );
            });

        $this->newLine();
        $this->info('── Summary ──');
        $this->table(['Metric', 'Count'], [
            ['Processed / normalized', $stats['processed']],
            ['Already normalized (skipped)', $stats['skipped_already_normalized']],
            ['Missing file (skipped)', $stats['skipped_missing']],
            ['Blank image or GD error (skipped)', $stats['skipped_blank_or_error']],
            ['Failed (exception)', $stats['failed']],
        ]);

        return 0;
    }

    /**
     * Normalize a single stored signature image at the given S3/public disk path.
     * Overwrites the file in place at the same path — no DB column changes needed
     * since the path itself doesn't change, only the image content.
     */
    private function processPath(
        DigitalSignatureService $svc,
        string $path,
        string $label,
        bool $dryRun,
        array &$stats
    ): void {
        try {
            $disk = null;
            try {
                if (Storage::disk('s3')->exists($path)) {
                    $disk = 's3';
                }
            } catch (\Throwable) {
                // S3 unreachable/misconfigured — fall through to public disk check.
            }
            if ($disk === null) {
                try {
                    if (Storage::disk('public')->exists($path)) {
                        $disk = 'public';
                    }
                } catch (\Throwable) {
                    // ignore — handled by the missing-file branch below
                }
            }

            if ($disk === null) {
                $this->line("  [missing]  {$label} — path '{$path}' not found on s3 or public disk");
                $stats['skipped_missing']++;
                return;
            }

            $imageData = Storage::disk($disk)->get($path);

            $info = @getimagesizefromstring($imageData);
            if ($info && $info[0] === self::NORMALIZED_WIDTH && $info[1] === self::NORMALIZED_HEIGHT) {
                // Already at standard canvas size — very likely already normalized.
                // Not a perfect guarantee, but safe/idempotent: re-running normalize
                // on an already-cropped+centered image is a no-op in practice since
                // the ink already fills the canvas at max scale.
                $stats['skipped_already_normalized']++;
                return;
            }

            $normalized = $svc->normalizeSignatureImage($imageData);

            if ($normalized === null) {
                $this->line("  [blank/err] {$label} — no ink detected or image could not be processed, left as-is");
                $stats['skipped_blank_or_error']++;
                return;
            }

            if ($dryRun) {
                $this->line("  [would fix] {$label} — path '{$path}' on '{$disk}' disk");
            } else {
                Storage::disk($disk)->put($path, $normalized, ['ContentType' => 'image/png']);
                $this->line("  [fixed]    {$label} — path '{$path}' on '{$disk}' disk");
            }

            $stats['processed']++;
        } catch (\Throwable $e) {
            $this->error("  [FAILED]   {$label} — {$e->getMessage()}");
            $stats['failed']++;
        }
    }
}
