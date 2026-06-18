<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->time('afternoon_break_start')->nullable()->after('lunch_end');
            $table->time('afternoon_break_end')->nullable()->after('afternoon_break_start');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['afternoon_break_start', 'afternoon_break_end']);
        });
    }
};
