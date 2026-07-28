<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_record_ila_dates', function (Blueprint $table) {
            // Grading an ILA date is decided the same way any other WAT
            // assessment is plotted — no later than 12:00 NN of the Friday
            // before its week (WatRuleService::plottingDeadline()). Defaults
            // to false: ILA stays a compliance-only log unless a teacher
            // opts a specific date in before that deadline.
            $table->boolean('is_graded')->default(false)->after('is_auto_generated');
            $table->foreignId('class_record_assessment_id')->nullable()->after('is_graded')
                ->constrained('class_record_assessments')->nullOnDelete();
            $table->foreignId('graded_by_id')->nullable()->after('class_record_assessment_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('graded_decided_at')->nullable()->after('graded_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('class_record_ila_dates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_record_assessment_id');
            $table->dropConstrainedForeignId('graded_by_id');
            $table->dropColumn(['is_graded', 'graded_decided_at']);
        });
    }
};
