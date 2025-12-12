<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employee_ipcrs_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_id')
                  ->constrained('employee_ipcrs') // your IPCR table
                  ->cascadeOnDelete();
            $table->foreignId('plan_id')
                  ->constrained('work_distribution_plans')
                  ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ipcr_id', 'plan_id']); // prevent duplicates
        });
    }

    public function down()
    {
        Schema::dropIfExists('employee_ipcr_plan');
    }
};
