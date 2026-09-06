<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opcr_indicators', function (Blueprint $table) {
            $table->unique('performance_indicator_id');
        });
    }

    public function down(): void
    {
        Schema::table('opcr_indicators', function (Blueprint $table) {
            $table->dropUnique(['performance_indicator_id']);
        });
    }
};
