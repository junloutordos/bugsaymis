<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->time('lunch_start_wed')->nullable()->after('lunch_end');
            $table->time('lunch_end_wed')->nullable()->after('lunch_start_wed');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['lunch_start_wed', 'lunch_end_wed']);
        });
    }
};
