<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            // Rating fields for a WDP-tagged materialized row (one row per
            // tagged plan) — mirrors ipcr_v2_support_items' naming. The
            // existing student_feedback_rating/supervisor_feedback_rating/
            // im_development_rating columns stay in use for untagged
            // (teaching-load) items, which keep the fixed CSC rubric.
            $table->unsignedTinyInteger('quality_rating')->nullable()->after('success_indicator');
            $table->unsignedTinyInteger('efficiency_rating')->nullable()->after('quality_rating');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->dropColumn(['quality_rating', 'efficiency_rating']);
        });
    }
};
