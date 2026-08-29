<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves Work Distribution Plan linking off the individual Designation row
 * and onto the DesignationCategory "mother record" instead — one tag on the
 * category applies to every designation under it, and therefore every
 * current and future holder of ANY of those designations, automatically.
 *
 * `designation_work_distribution_plan` is safe to drop outright: it was
 * deployed in the immediately preceding commit and confirmed empty in
 * production (0 rows) before this migration was written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('designation_work_distribution_plan');

        Schema::create('designation_category_work_distribution_plan', function (Blueprint $table) {
            $table->unsignedBigInteger('designation_category_id');
            $table->unsignedBigInteger('work_distribution_plan_id');
            $table->timestamps();

            $table->foreign('designation_category_id', 'desig_cat_wdp_cat_fk')
                  ->references('id')->on('designation_categories')->cascadeOnDelete();
            $table->foreign('work_distribution_plan_id', 'desig_cat_wdp_wdp_fk')
                  ->references('id')->on('work_distribution_plans')->cascadeOnDelete();
            $table->primary(['designation_category_id', 'work_distribution_plan_id'], 'desig_cat_wdp_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designation_category_work_distribution_plan');

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
};
