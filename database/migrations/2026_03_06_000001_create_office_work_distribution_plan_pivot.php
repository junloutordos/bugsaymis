<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_work_distribution_plan', function (Blueprint $table) {
            $table->foreignId('work_distribution_plan_id')
                  ->constrained('work_distribution_plans')
                  ->cascadeOnDelete();
            $table->foreignId('office_id')
                  ->constrained('offices')
                  ->cascadeOnDelete();
            $table->primary(['work_distribution_plan_id', 'office_id']);
        });

        Schema::table('work_distribution_plans', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropColumn('office_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_work_distribution_plan');

        Schema::table('work_distribution_plans', function (Blueprint $table) {
            $table->foreignId('office_id')
                  ->nullable()
                  ->after('office_involved')
                  ->constrained('offices')
                  ->nullOnDelete();
        });
    }
};
