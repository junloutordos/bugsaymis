<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('load_assignment_work_distribution_plan', function (Blueprint $table) {
            $table->unsignedBigInteger('load_assignment_id');
            $table->unsignedBigInteger('work_distribution_plan_id');
            $table->timestamps();

            $table->foreign('load_assignment_id', 'la_wdp_la_fk')
                  ->references('id')->on('load_assignments')->cascadeOnDelete();
            $table->foreign('work_distribution_plan_id', 'la_wdp_wdp_fk')
                  ->references('id')->on('work_distribution_plans')->cascadeOnDelete();
            $table->primary(['load_assignment_id', 'work_distribution_plan_id'], 'la_wdp_primary');
        });

        Schema::create('faculty_committee_assignment_work_distribution_plan', function (Blueprint $table) {
            $table->unsignedBigInteger('faculty_committee_assignment_id');
            $table->unsignedBigInteger('work_distribution_plan_id');
            $table->timestamps();

            $table->foreign('faculty_committee_assignment_id', 'fcawdp_fca_fk')
                  ->references('id')->on('faculty_committee_assignments')->cascadeOnDelete();
            $table->foreign('work_distribution_plan_id', 'fcawdp_wdp_fk')
                  ->references('id')->on('work_distribution_plans')->cascadeOnDelete();
            $table->primary(['faculty_committee_assignment_id', 'work_distribution_plan_id'], 'fca_wdp_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_committee_assignment_work_distribution_plan');
        Schema::dropIfExists('load_assignment_work_distribution_plan');
    }
};
