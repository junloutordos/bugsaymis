<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_ipcrs_plan_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_id')->constrained('employee_ipcrs_v2')->cascadeOnDelete();
            $table->enum('function_type', ['strategic', 'core', 'support']);
            // Snapshotted at attach time — a later edit to the master
            // WorkDistributionPlan/OpcrTemplateItem weight never
            // retroactively changes an already-rated row.
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->foreignId('plan_id')->nullable()->constrained('work_distribution_plans')->nullOnDelete();
            $table->foreignId('opcr_template_item_id')->nullable()->constrained('opcr_template_items')->nullOnDelete();
            $table->text('individual_target')->nullable();
            $table->text('accomplishment')->nullable();
            $table->string('mov_link')->nullable();
            $table->tinyInteger('self_quality')->nullable();
            $table->tinyInteger('self_efficiency')->nullable();
            $table->tinyInteger('self_timeliness')->nullable();
            $table->decimal('self_average', 5, 2)->nullable();
            $table->tinyInteger('sup_quality')->nullable();
            $table->tinyInteger('sup_efficiency')->nullable();
            $table->tinyInteger('sup_timeliness')->nullable();
            $table->decimal('sup_average', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ipcrs_plan_v2');
    }
};
