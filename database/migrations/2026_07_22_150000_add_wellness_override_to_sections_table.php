<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->time('wellness_start_mon')->nullable()->after('white_space_end_fri');
            $table->time('wellness_end_mon')->nullable()->after('wellness_start_mon');
            $table->time('wellness_start_tue')->nullable()->after('wellness_end_mon');
            $table->time('wellness_end_tue')->nullable()->after('wellness_start_tue');
            $table->time('wellness_start_wed')->nullable()->after('wellness_end_tue');
            $table->time('wellness_end_wed')->nullable()->after('wellness_start_wed');
            $table->time('wellness_start_thu')->nullable()->after('wellness_end_wed');
            $table->time('wellness_end_thu')->nullable()->after('wellness_start_thu');
            $table->time('wellness_start_fri')->nullable()->after('wellness_end_thu');
            $table->time('wellness_end_fri')->nullable()->after('wellness_start_fri');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn([
                'wellness_start_mon', 'wellness_end_mon',
                'wellness_start_tue', 'wellness_end_tue',
                'wellness_start_wed', 'wellness_end_wed',
                'wellness_start_thu', 'wellness_end_thu',
                'wellness_start_fri', 'wellness_end_fri',
            ]);
        });
    }
};
