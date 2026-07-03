<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_activity_meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('ams_activities')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('am_snacks')->default(false);
            $table->boolean('lunch')->default(false);
            $table->boolean('pm_snacks')->default(false);
            $table->boolean('dinner')->default(false);
            $table->timestamps();

            $table->unique(['activity_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_activity_meal_plans');
    }
};
