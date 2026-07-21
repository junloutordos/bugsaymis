<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->time('recess_start_mon')->nullable()->after('lunch_end_fri');
            $table->time('recess_end_mon')->nullable()->after('recess_start_mon');
            $table->time('recess_start_tue')->nullable()->after('recess_end_mon');
            $table->time('recess_end_tue')->nullable()->after('recess_start_tue');
            $table->time('recess_start_wed')->nullable()->after('recess_end_tue');
            $table->time('recess_end_wed')->nullable()->after('recess_start_wed');
            $table->time('recess_start_thu')->nullable()->after('recess_end_wed');
            $table->time('recess_end_thu')->nullable()->after('recess_start_thu');
            $table->time('recess_start_fri')->nullable()->after('recess_end_thu');
            $table->time('recess_end_fri')->nullable()->after('recess_start_fri');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn([
                'recess_start_mon', 'recess_end_mon',
                'recess_start_tue', 'recess_end_tue',
                'recess_start_wed', 'recess_end_wed',
                'recess_start_thu', 'recess_end_thu',
                'recess_start_fri', 'recess_end_fri',
            ]);
        });
    }
};
