<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WatRuleService::isMajor() previously judged "major" purely from a
 * category's weight share, blind to compliance-mode grading options (e.g.
 * Values Education's checkbox/Pass-Completed setup) — every compliance-mode
 * assessment with a per-item weight share >= 10% was incorrectly stored as
 * is_major=true (confirmed: all plotted Values Education assessments in
 * prod). Recompute is_major for existing rows now that the service excludes
 * compliance-mode categories from the "major" concept entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('class_record_assessments')
            ->join('grading_categories', 'class_record_assessments.grading_category_id', '=', 'grading_categories.id')
            ->join('grading_options', 'grading_categories.grading_option_id', '=', 'grading_options.id')
            ->where('grading_options.grading_mode', 'compliance')
            ->update(['class_record_assessments.is_major' => false]);
    }

    public function down(): void
    {
        // Restore the pre-fix weight-only computation (no compliance-mode
        // exemption) for the rows this migration touched.
        DB::table('class_record_assessments')
            ->join('grading_categories', 'class_record_assessments.grading_category_id', '=', 'grading_categories.id')
            ->join('grading_options', 'grading_categories.grading_option_id', '=', 'grading_options.id')
            ->where('grading_options.grading_mode', 'compliance')
            ->update([
                'class_record_assessments.is_major' => DB::raw(
                    "(class_record_assessments.assessment_type IN ('long_test_1', 'long_test_2')"
                    .' OR ROUND(grading_categories.weight / GREATEST(grading_categories.max_assessments, 1), 6) >= 0.10)'
                ),
            ]);
    }
};
