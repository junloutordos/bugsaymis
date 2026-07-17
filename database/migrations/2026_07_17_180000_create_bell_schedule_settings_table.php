<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editable special bell-schedule windows that aren't full day timetables —
     * Wednesday wellness / ALP / activity cutoff, Friday flag retreat, the G8
     * overflow periods, and the Friday-ILA / Wednesday-full grade lists. Each is
     * stored as one JSON value keyed by its SchedulingConstants name; absence of
     * a row = fall back to the hardcoded default, so an empty table reproduces
     * today's behavior exactly. Additive, blue-green safe.
     */
    public function up(): void
    {
        Schema::create('bell_schedule_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->json('value');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bell_schedule_settings');
    }
};
