<?php

namespace App\Console\Commands;

use App\Models\EmployeeIPCR;
use App\Models\IPCRRatingPeriod;
use Illuminate\Console\Command;

/**
 * One-off, idempotent backfill: link employee_ipcrs.rating_period_id from the
 * legacy free-text rating_period string. Exact label match first, then
 * year-regex + semester-keyword fallback. Unmatched rows are reported and
 * left NULL for manual review.
 */
class BackfillIPCRRatingPeriods extends Command
{
    protected $signature = 'ipcr:backfill-rating-periods {--dry-run : Report matches without writing}';

    protected $description = 'Link legacy free-text IPCR rating periods to ipcr_rating_periods rows';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $periodsByLabel = IPCRRatingPeriod::all()->keyBy(fn ($p) => mb_strtolower(trim($p->label)));

        $rows = EmployeeIPCR::whereNull('rating_period_id')->get(['id', 'rating_period']);

        if ($rows->isEmpty()) {
            $this->info('Nothing to backfill — all IPCRs already have a rating_period_id.');
            return self::SUCCESS;
        }

        $matched   = 0;
        $unmatched = [];

        foreach ($rows as $row) {
            $period = $this->resolvePeriod($row->rating_period, $periodsByLabel);

            if (! $period) {
                $unmatched[] = $row;
                continue;
            }

            $matched++;
            $this->line(($dryRun ? '[dry-run] ' : '')."IPCR #{$row->id}: \"{$row->rating_period}\" → period #{$period->id} ({$period->label})");

            if (! $dryRun) {
                EmployeeIPCR::whereKey($row->id)->update(['rating_period_id' => $period->id]);
            }
        }

        $this->newLine();
        $this->info("Matched: {$matched} / {$rows->count()}".($dryRun ? ' (dry-run — nothing written)' : ''));

        if ($unmatched) {
            $this->warn('Unmatched IPCRs (rating_period_id left NULL):');
            foreach ($unmatched as $row) {
                $this->warn("  IPCR #{$row->id}: \"{$row->rating_period}\"");
            }
        }

        return self::SUCCESS;
    }

    private function resolvePeriod(?string $raw, $periodsByLabel): ?IPCRRatingPeriod
    {
        if (! $raw) {
            return null;
        }

        $normalized = mb_strtolower(trim($raw));

        if ($periodsByLabel->has($normalized)) {
            return $periodsByLabel->get($normalized);
        }

        // Fallback: year + semester keyword
        if (! preg_match('/\b(20\d{2})\b/', $raw, $m)) {
            return null;
        }
        $year = (int) $m[1];

        $semester = null;
        if (preg_match('/jan|first sem|1st sem/i', $raw)) {
            $semester = 1;
        } elseif (preg_match('/jul|second sem|2nd sem/i', $raw)) {
            $semester = 2;
        }

        if (! $semester) {
            return null;
        }

        return IPCRRatingPeriod::where('year', $year)->where('semester', $semester)->first();
    }
}
