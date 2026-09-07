<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_functions', function (Blueprint $table) {
            $table->dropForeign(['work_distribution_plan_id']);
            $table->dropColumn('work_distribution_plan_id');
        });

        Schema::create('employee_function_work_distribution_plan', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_function_id');
            $table->unsignedBigInteger('work_distribution_plan_id');
            $table->foreign('employee_function_id', 'efwdp_ef_fk')
                ->references('id')->on('employee_functions')->cascadeOnDelete();
            $table->foreign('work_distribution_plan_id', 'efwdp_wdp_fk')
                ->references('id')->on('work_distribution_plans')->cascadeOnDelete();
            $table->primary(['employee_function_id', 'work_distribution_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_function_work_distribution_plan');

        Schema::table('employee_functions', function (Blueprint $table) {
            $table->foreignId('work_distribution_plan_id')->nullable()
                ->after('load_assignment_id')
                ->constrained('work_distribution_plans')->nullOnDelete();
        });
    }
};
