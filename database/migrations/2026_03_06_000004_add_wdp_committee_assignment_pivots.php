<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('work_distribution_plans', 'free_text_involved')) {
            Schema::table('work_distribution_plans', function (Blueprint $table) {
                $table->text('free_text_involved')->nullable()->after('office_involved');
            });
        }

        Schema::dropIfExists('committee_work_distribution_plan');
        Schema::create('committee_work_distribution_plan', function (Blueprint $table) {
            $table->unsignedBigInteger('work_distribution_plan_id');
            $table->unsignedBigInteger('committee_id');
            $table->foreign('work_distribution_plan_id', 'cwdp_wdp_fk')
                  ->references('id')->on('work_distribution_plans')->cascadeOnDelete();
            $table->foreign('committee_id', 'cwdp_committee_fk')
                  ->references('id')->on('committees')->cascadeOnDelete();
            $table->primary(['work_distribution_plan_id', 'committee_id']);
        });

        Schema::dropIfExists('special_assignment_work_distribution_plan');
        Schema::create('special_assignment_work_distribution_plan', function (Blueprint $table) {
            $table->unsignedBigInteger('work_distribution_plan_id');
            $table->unsignedBigInteger('special_assignment_id');
            $table->foreign('work_distribution_plan_id', 'sawdp_wdp_fk')
                  ->references('id')->on('work_distribution_plans')->cascadeOnDelete();
            $table->foreign('special_assignment_id', 'sawdp_sa_fk')
                  ->references('id')->on('special_assignments')->cascadeOnDelete();
            $table->primary(['work_distribution_plan_id', 'special_assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_assignment_work_distribution_plan');
        Schema::dropIfExists('committee_work_distribution_plan');
        Schema::table('work_distribution_plans', function (Blueprint $table) {
            $table->dropColumn('free_text_involved');
        });
    }
};
