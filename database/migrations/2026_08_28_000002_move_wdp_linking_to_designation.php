<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves Work Distribution Plan linking off the individual LoadAssignment row
 * and onto the Designation "mother record" instead — one tag on the
 * designation applies to every current and future holder automatically.
 *
 * `load_assignment_work_distribution_plan` is safe to drop outright: it was
 * deployed in the immediately preceding commit and confirmed empty in
 * production (0 rows) before this migration was written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('load_assignment_work_distribution_plan');

        Schema::create('designation_work_distribution_plan', function (Blueprint $table) {
            $table->unsignedBigInteger('designation_id');
            $table->unsignedBigInteger('work_distribution_plan_id');
            $table->timestamps();

            $table->foreign('designation_id', 'desig_wdp_desig_fk')
                  ->references('id')->on('designations')->cascadeOnDelete();
            $table->foreign('work_distribution_plan_id', 'desig_wdp_wdp_fk')
                  ->references('id')->on('work_distribution_plans')->cascadeOnDelete();
            $table->primary(['designation_id', 'work_distribution_plan_id'], 'desig_wdp_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designation_work_distribution_plan');

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
    }
};
