<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->time('lunch_start_mon')->nullable()->after('lunch_end_wed');
            $table->time('lunch_end_mon')->nullable()->after('lunch_start_mon');
            $table->time('lunch_start_tue')->nullable()->after('lunch_end_mon');
            $table->time('lunch_end_tue')->nullable()->after('lunch_start_tue');
            $table->time('lunch_start_thu')->nullable()->after('lunch_end_tue');
            $table->time('lunch_end_thu')->nullable()->after('lunch_start_thu');
            $table->time('lunch_start_fri')->nullable()->after('lunch_end_thu');
            $table->time('lunch_end_fri')->nullable()->after('lunch_start_fri');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn([
                'lunch_start_mon', 'lunch_end_mon',
                'lunch_start_tue', 'lunch_end_tue',
                'lunch_start_thu', 'lunch_end_thu',
                'lunch_start_fri', 'lunch_end_fri',
            ]);
        });
    }
};
