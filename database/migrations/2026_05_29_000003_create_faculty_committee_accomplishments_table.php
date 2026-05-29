<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_committee_accomplishments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faculty_committee_assignment_id');
            $table->unsignedBigInteger('work_distribution_plan_id');

            $table->foreign('faculty_committee_assignment_id', 'fca_assignment_fk')
                ->references('id')->on('faculty_committee_assignments')
                ->cascadeOnDelete();
            $table->foreign('work_distribution_plan_id', 'fca_plan_fk')
                ->references('id')->on('work_distribution_plans')
                ->cascadeOnDelete();
            $table->text('accomplishment')->nullable();
            $table->string('mov_link', 255)->nullable();
            $table->decimal('sup_quality', 3, 2)->nullable();
            $table->decimal('sup_efficiency', 3, 2)->nullable();
            $table->decimal('sup_timeliness', 3, 2)->nullable();
            $table->decimal('sup_average', 3, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['faculty_committee_assignment_id', 'work_distribution_plan_id'],
                'fca_assignment_plan_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_committee_accomplishments');
    }
};
