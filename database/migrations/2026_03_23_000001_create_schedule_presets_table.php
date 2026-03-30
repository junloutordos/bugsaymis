<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('e.g. Plantilla Non-Teaching, Plantilla Teaching');
            $table->enum('schedule_type', ['fixed', 'shifting'])->default('fixed');
            $table->json('work_days')->nullable()->comment('["Mon","Tue","Wed","Thu","Fri"]');
            $table->time('time_in');
            $table->time('time_out');
            $table->unsignedSmallInteger('grace_period_minutes')->default(15);
            $table->unsignedSmallInteger('late_threshold_minutes')->default(240);
            $table->unsignedSmallInteger('half_day_hours')->default(4);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_presets');
    }
};
