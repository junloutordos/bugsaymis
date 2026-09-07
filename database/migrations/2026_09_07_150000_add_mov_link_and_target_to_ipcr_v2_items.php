<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->string('mov_link', 500)->nullable()->after('actual_accomplishment');
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->text('target')->nullable()->after('success_indicator');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->dropColumn('mov_link');
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->dropColumn('target');
        });
    }
};
