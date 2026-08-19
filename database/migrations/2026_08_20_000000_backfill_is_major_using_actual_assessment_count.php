<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WatRuleService::isMajor() divided a category's weight by its CONFIGURED
 * max_assessments cap — but nothing enforces that cap, and the Setup tab
 * explicitly tolerates plotting more rows than it (Show.vue's buildDraft:
 * "show at least max_assessments rows OR all saved rows, whichever is
 * more"). When the actual plotted count exceeds the cap, each item's true
 * share is smaller than the stale cap-based calculation, and it can end up
 * wrongly flagged major (confirmed in prod: 159 rows across 39 distinct
 * quarter+category combinations, e.g. an English 4 "Formative" category
 * configured for 1 assessment at 25% weight with 6 actually plotted — each
 * really worth 25%/6 ≈ 4.17%, but all 6 stored as major from 25%/1 = 25%).
 * Recompute is_major using the larger of the configured cap and the actual
 * plotted count per quarter+category, same formula WatRuleService::isMajor()
 * now applies at save time.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('class_record_assessments as a')
            ->join('grading_categories as g', 'a.grading_category_id', '=', 'g.id')
            ->leftJoin('grading_options as go', 'g.grading_option_id', '=', 'go.id')
            ->joinSub(
                DB::table('class_record_assessments')
                    ->select('class_record_quarter_id', 'grading_category_id')
                    ->selectRaw('count(*) as actual_count')
                    ->groupBy('class_record_quarter_id', 'grading_category_id'),
                'counts',
                function ($join) {
                    $join->on('counts.class_record_quarter_id', '=', 'a.class_record_quarter_id')
                        ->on('counts.grading_category_id', '=', 'a.grading_category_id');
                }
            )
            ->update([
                'a.is_major' => DB::raw(
                    "(COALESCE(go.grading_mode, 'numeric') <> 'compliance'"
                    .' AND ROUND(g.weight / GREATEST(g.max_assessments, counts.actual_count, 1), 6) >= 0.10)'
                ),
            ]);
    }

    public function down(): void
    {
        // Restore the pre-fix, cap-only computation (no actual-count
        // awareness) for every row, matching the Aug 19 compliance-mode
        // migration's formula.
        DB::table('class_record_assessments as a')
            ->join('grading_categories as g', 'a.grading_category_id', '=', 'g.id')
            ->leftJoin('grading_options as go', 'g.grading_option_id', '=', 'go.id')
            ->update([
                'a.is_major' => DB::raw(
                    "(COALESCE(go.grading_mode, 'numeric') <> 'compliance'"
                    .' AND ROUND(g.weight / GREATEST(g.max_assessments, 1), 6) >= 0.10)'
                ),
            ]);
    }
};
