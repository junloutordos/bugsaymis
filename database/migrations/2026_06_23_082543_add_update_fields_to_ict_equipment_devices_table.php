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
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->timestamp('last_update_attempted_at')->nullable()->after('network_location_changed_at');
            $table->string('last_update_result', 20)->nullable()->after('last_update_attempted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->dropColumn(['last_update_attempted_at', 'last_update_result']);
        });
    }
};
