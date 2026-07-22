<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->time('white_space_start_mon')->nullable()->after('recess_end_fri');
            $table->time('white_space_end_mon')->nullable()->after('white_space_start_mon');
            $table->time('white_space_start_tue')->nullable()->after('white_space_end_mon');
            $table->time('white_space_end_tue')->nullable()->after('white_space_start_tue');
            $table->time('white_space_start_wed')->nullable()->after('white_space_end_tue');
            $table->time('white_space_end_wed')->nullable()->after('white_space_start_wed');
            $table->time('white_space_start_thu')->nullable()->after('white_space_end_wed');
            $table->time('white_space_end_thu')->nullable()->after('white_space_start_thu');
            $table->time('white_space_start_fri')->nullable()->after('white_space_end_thu');
            $table->time('white_space_end_fri')->nullable()->after('white_space_start_fri');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn([
                'white_space_start_mon', 'white_space_end_mon',
                'white_space_start_tue', 'white_space_end_tue',
                'white_space_start_wed', 'white_space_end_wed',
                'white_space_start_thu', 'white_space_end_thu',
                'white_space_start_fri', 'white_space_end_fri',
            ]);
        });
    }
};
