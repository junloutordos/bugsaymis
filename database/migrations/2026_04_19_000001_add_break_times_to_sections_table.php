<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->time('recess_start')->nullable()->after('capacity');
            $table->time('recess_end')->nullable()->after('recess_start');
            $table->time('lunch_start')->nullable()->after('recess_end');
            $table->time('lunch_end')->nullable()->after('lunch_start');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['recess_start', 'recess_end', 'lunch_start', 'lunch_end']);
        });
    }
};
