<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ict_equipment_devices', 'network_location')) {
            return;
        }

        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->enum('network_location', ['on_campus', 'off_campus', 'unknown'])
                ->default('unknown')
                ->after('agent_version');
            $table->timestamp('network_location_changed_at')->nullable()->after('network_location');
        });
    }

    public function down(): void
    {
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->dropColumn(['network_location', 'network_location_changed_at']);
        });
    }
};
