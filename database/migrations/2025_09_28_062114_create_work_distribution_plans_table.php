<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkDistributionPlansTable extends Migration
{
    public function up()
    {
        Schema::create('work_distribution_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_indicator_id')
                ->constrained('performance_indicators')
                ->onDelete('cascade'); // if indicator is deleted, cascade
            $table->text('success_indicator')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        // Pivot table for personnel assignments
        Schema::create('plan_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_distribution_plan_id')
                ->constrained('work_distribution_plans')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plan_user');
        Schema::dropIfExists('work_distribution_plans');
    }
}
