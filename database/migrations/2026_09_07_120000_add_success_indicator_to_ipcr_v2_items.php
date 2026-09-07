<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->text('success_indicator')->nullable()->after('weight_percent');
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->text('success_indicator')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->dropColumn('success_indicator');
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->dropColumn('success_indicator');
        });
    }
};
