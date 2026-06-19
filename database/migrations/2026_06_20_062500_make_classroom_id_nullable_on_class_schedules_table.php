<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cross-section elective offerings (ELEC-* sections) are intentionally
     * scheduled without a dedicated classroom — the AI scheduler and conflict
     * detection already treat a missing classroom_id as valid, but the column
     * was still NOT NULL, so saving those sessions failed on insert.
     */
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable(false)->change();
        });
    }
};
