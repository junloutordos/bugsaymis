<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            $table->unsignedTinyInteger('month')->nullable()->after('year');

            $table->dropIndex(['type', 'year', 'series_no']);
            $table->index(['type', 'year', 'month', 'series_no']);
        });
    }

    public function down(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            $table->dropIndex(['type', 'year', 'month', 'series_no']);
            $table->index(['type', 'year', 'series_no']);

            $table->dropColumn('month');
        });
    }
};
