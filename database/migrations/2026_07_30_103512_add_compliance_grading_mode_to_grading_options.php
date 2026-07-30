<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance-mode grading — for subjects like Values Education where a
 * "score" is really a checkbox (did the student comply with the activity?)
 * and the quarter/school-year output is a completion percentage + a
 * qualitative remark instead of a numeric GE/adjectival.
 *
 * All fields are nullable/defaulted so every existing (numeric) grading
 * option is completely unaffected — grading_mode defaults to 'numeric' and
 * the compliance_* columns are only ever read when grading_mode is
 * 'compliance'. Every rule is per-option and admin-editable (no hardcoded
 * policy), per the approved design:
 *   - compliance_pass_threshold: quarter-level cutoff (e.g. 0.75 = 75%)
 *   - compliance_sy_rule: how the 4 quarters combine into a school-year
 *     remark ('all_quarters_pass' or 'average_threshold', both compared
 *     against the same pass threshold)
 *   - compliance_pass_label / compliance_fail_label: the remark text itself
 *     (default "Completed" / "Not Completed"), also editable
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_options', function (Blueprint $table) {
            $table->enum('grading_mode', ['numeric', 'compliance'])
                ->default('numeric')->after('description');
            $table->decimal('compliance_pass_threshold', 4, 3)->nullable()->after('grading_mode');
            $table->enum('compliance_sy_rule', ['all_quarters_pass', 'average_threshold'])
                ->default('all_quarters_pass')->after('compliance_pass_threshold');
            $table->string('compliance_pass_label', 50)->default('Completed')->after('compliance_sy_rule');
            $table->string('compliance_fail_label', 50)->default('Not Completed')->after('compliance_pass_label');
        });
    }

    public function down(): void
    {
        Schema::table('grading_options', function (Blueprint $table) {
            $table->dropColumn([
                'grading_mode',
                'compliance_pass_threshold',
                'compliance_sy_rule',
                'compliance_pass_label',
                'compliance_fail_label',
            ]);
        });
    }
};
