<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->boolean('is_advance')->default(false)->after('travel_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->dropColumn('is_advance');
        });
    }
};
