<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teacher_tap_logs', function (Blueprint $table) {
            $table->string('channel', 10)->default('nfc')->after('classroom_id');
            $table->string('ip_address', 45)->nullable()->after('channel');
            $table->string('location_status', 20)->nullable()->after('ip_address');
            $table->string('network_status', 20)->nullable()->after('location_status');
            $table->decimal('latitude', 10, 7)->nullable()->after('network_status');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_tap_logs', function (Blueprint $table) {
            $table->dropColumn(['channel', 'ip_address', 'location_status', 'network_status', 'latitude', 'longitude']);
        });
    }
};
