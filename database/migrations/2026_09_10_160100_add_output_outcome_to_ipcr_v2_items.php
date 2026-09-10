<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot of EmployeeFunction::output_outcome at generation/sync time —
     * same frozen-at-generation pattern as label/success_indicator, so a
     * later edit to the source function never silently rewrites an
     * already-materialized IPCR V2 item.
     */
    public function up(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->text('output_outcome')->nullable()->after('label');
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->text('output_outcome')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->dropColumn('output_outcome');
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->dropColumn('output_outcome');
        });
    }
};
