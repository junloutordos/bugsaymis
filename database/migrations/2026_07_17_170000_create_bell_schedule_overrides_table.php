<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editable bell-schedule timetables. Each row overrides one hardcoded
     * SchedulingConstants timetable (keyed by its constant name, e.g.
     * MONDAY_G7G8) with an admin-edited sequence of {start,end,type,label}
     * blocks. Absence of a row = fall back to the hardcoded default, so an
     * empty table means today's behavior exactly. Additive, blue-green safe.
     */
    public function up(): void
    {
        Schema::create('bell_schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('timetable_key')->unique();
            $table->json('rows');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bell_schedule_overrides');
    }
};
