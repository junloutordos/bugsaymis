<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-adds per-Designation Work Distribution Plan tagging as an ADDITIONAL,
 * optional layer on top of the DesignationCategory tagging shipped earlier
 * today — some designations within a category (e.g. individual
 * coordinatorships under "Coordinatorship") need their own WDP(s) alongside
 * whatever the category already tags. A faculty member holding such a
 * designation gets the union of both.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }
};
