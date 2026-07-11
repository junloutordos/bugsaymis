<?php

namespace App\Console\Commands;

use App\Models\EmployeeIPCRPlan;
use Illuminate\Console\Command;

/**
 * One-off, idempotent conversion of legacy percentage-scale (0–100) IPCR
 * Q/E/T ratings to the CSC SPMS 5-point scale. Values already within 1–5
 * are left untouched; values above 5 are treated as percentages and mapped
 * with clamp(round(v / 20), 1, 5). Averages are recomputed afterwards.
 *
 * Run with --dry-run first and review the report before converting.
 */
class NormalizeIPCRRatingScale extends Command
{
    protected $signature = 'ipcr:normalize-rating-scale {--dry-run : Report conversions without writing}';

    protected $description = 'Convert legacy 0–100 IPCR Q/E/T ratings to the CSC 5-point scale';

    private const FIELDS = [
        'self_quality', 'self_efficiency', 'self_timeliness',
        'sup_quality', 'sup_efficiency', 'sup_timeliness',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = EmployeeIPCRPlan::where(function ($q) {
            foreach (self::FIELDS as $field) {
                $q->orWhere($field, '>', 5);
            }
        })->get();

        if ($rows->isEmpty()) {
            $this->info('All Q/E/T ratings are already on the 1–5 scale. Nothing to convert.');
            return self::SUCCESS;
        }

        $converted = 0;

        foreach ($rows as $row) {
            $changes = [];

            foreach (self::FIELDS as $field) {
                $value = $row->{$field};
                if ($value !== null && $value > 5) {
                    $changes[$field] = max(1, min(5, (int) round($value / 20)));
                }
            }

            if (! $changes) {
                continue;
            }

            // Recompute averages from the post-conversion values
            foreach (['self', 'sup'] as $prefix) {
                $values = collect(["{$prefix}_quality", "{$prefix}_efficiency", "{$prefix}_timeliness"])
                    ->map(fn ($f) => $changes[$f] ?? $row->{$f})
                    ->filter(fn ($v) => $v !== null);

                $changes["{$prefix}_average"] = $values->count() ? round($values->avg(), 2) : null;
            }

            $converted++;
            $this->line(($dryRun ? '[dry-run] ' : '')."Pivot #{$row->id} (ipcr {$row->ipcr_id}, plan {$row->plan_id}): ".json_encode($changes));

            if (! $dryRun) {
                EmployeeIPCRPlan::whereKey($row->id)->update($changes);
            }
        }

        $this->newLine();
        $this->info("Converted {$converted} pivot row(s)".($dryRun ? ' (dry-run — nothing written)' : '').'.');

        return self::SUCCESS;
    }
}
