<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every Values Education (compliance-mode) assessment ever created had
 * is_graded=false / max_score=0 — the Setup tab's generic "Graded/
 * Non-graded" toggle reads as "no numeric grade" to a teacher, which is
 * true but not the same thing. That zeroed max_score, which in turn made
 * ScoreGrid.vue's is_graded!==false filter drop the assessment from the
 * score grid entirely — the compliance checkbox never rendered, and every
 * VE class record showed 0% / the fail remark for every student (confirmed:
 * 100/100 VE assessments in prod, zero class_record_scores rows against any
 * of them — nothing to lose). Compliance-mode assessments have no
 * "non-graded" concept — every one of them IS the checkbox being tracked —
 * so backfill them to the only valid state and stop trusting the client for
 * this going forward (see ClassRecordAssessmentController::upsert()/plot()).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('class_record_assessments')
            ->join('grading_categories', 'class_record_assessments.grading_category_id', '=', 'grading_categories.id')
            ->join('grading_options', 'grading_categories.grading_option_id', '=', 'grading_options.id')
            ->where('grading_options.grading_mode', 'compliance')
            ->where('class_record_assessments.is_graded', false)
            ->update([
                'class_record_assessments.is_graded' => true,
                'class_record_assessments.max_score' => 1,
            ]);
    }

    public function down(): void
    {
        // Restore the pre-fix state for the rows this migration touched.
        // Only reverts rows still at max_score=1 exactly (this migration's
        // signature value) — assessments a teacher has since re-saved with a
        // different max_score post-fix are left alone.
        DB::table('class_record_assessments')
            ->join('grading_categories', 'class_record_assessments.grading_category_id', '=', 'grading_categories.id')
            ->join('grading_options', 'grading_categories.grading_option_id', '=', 'grading_options.id')
            ->where('grading_options.grading_mode', 'compliance')
            ->where('class_record_assessments.is_graded', true)
            ->where('class_record_assessments.max_score', 1)
            ->update([
                'class_record_assessments.is_graded' => false,
                'class_record_assessments.max_score' => 0,
            ]);
    }
};
