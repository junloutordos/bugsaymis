<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * assessment_type is now derived from the grading category (never picked by
 * the teacher) — recompute it for the rows saved while the Type select was
 * live. Mirrors WatRuleService::deriveType(); unknown codes stay null and
 * display via the category-name fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        $set = function (array $codes, ?int $number, string $type) {
            DB::table('class_record_assessments')
                ->join('grading_categories', 'class_record_assessments.grading_category_id', '=', 'grading_categories.id')
                ->whereIn('grading_categories.code', $codes)
                ->when($number !== null, fn ($q) => $q->where('class_record_assessments.assessment_number', $number))
                ->update(['class_record_assessments.assessment_type' => $type]);
        };

        $set(['FA', 'QZ'],       null, 'formative');
        $set(['AA', 'P1', 'P2'], null, 'alternative');
        $set(['ILA'],            null, 'ila');
        $set(['QE', 'SE', 'PE'], 1,    'long_test_1');
        $set(['QE', 'SE', 'PE'], 2,    'long_test_2');

        // Recompute is_major with the fixed 10%-boundary rule (float division
        // made 0.30/3 fail the >= 0.10 check on rows saved before this deploy)
        DB::table('class_record_assessments')
            ->join('grading_categories', 'class_record_assessments.grading_category_id', '=', 'grading_categories.id')
            ->update([
                'class_record_assessments.is_major' => DB::raw(
                    "(class_record_assessments.assessment_type IN ('long_test_1', 'long_test_2')"
                    . ' OR ROUND(grading_categories.weight / GREATEST(grading_categories.max_assessments, 1), 6) >= 0.10)'
                ),
            ]);
    }

    public function down(): void
    {
        // Teacher-picked values can't be restored; null resets to the
        // category-name display fallback.
        DB::table('class_record_assessments')->update(['assessment_type' => null]);
    }
};
