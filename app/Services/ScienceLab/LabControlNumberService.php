<?php

namespace App\Services\ScienceLab;

use Illuminate\Support\Facades\DB;

/**
 * Generates unique control numbers for laboratory documents.
 * Format: {PREFIX}-{YEAR}-{NNNN} e.g. LAB-RES-2026-0001
 */
class LabControlNumberService
{
    public const RESERVATION = 'LAB-RES';
    public const EQUIPMENT   = 'LAB-EQP';
    public const REAGENT     = 'LAB-RGT';
    public const REPAIR      = 'LAB-RPR';
    public const BUNDLE      = 'LAB-REQ';

    /**
     * Next control number for the given prefix + table/column, sequential per year.
     * Wrapped in a transaction with a row lock on the max to avoid collisions.
     */
    public function next(string $prefix, string $table, string $column = 'control_no'): string
    {
        $year = now()->year;
        $like = "{$prefix}-{$year}-%";

        return DB::transaction(function () use ($prefix, $table, $column, $year, $like) {
            $last = DB::table($table)
                ->where($column, 'like', $like)
                ->lockForUpdate()
                ->orderByDesc($column)
                ->value($column);

            $seq = 1;
            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                $seq = ((int) $m[1]) + 1;
            }

            return sprintf('%s-%d-%04d', $prefix, $year, $seq);
        });
    }
}
