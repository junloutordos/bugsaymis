<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->string('network_status')->nullable()->after('distance_meters')
                ->comment('trusted, unconfigured, unknown');
        });
    }

    public function down(): void
    {
        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->dropColumn('network_status');
        });
    }
};
