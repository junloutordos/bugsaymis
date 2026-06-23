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
            $table->unsignedTinyInteger('health_score')->nullable()->after('last_update_result');
            $table->unsignedTinyInteger('risk_score')->nullable()->after('health_score');
            $table->string('risk_tier', 20)->nullable()->after('risk_score');
            $table->timestamp('last_full_inventory_at')->nullable()->after('risk_tier');
            $table->timestamp('last_diagnostics_at')->nullable()->after('last_full_inventory_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->dropColumn(['health_score', 'risk_score', 'risk_tier', 'last_full_inventory_at', 'last_diagnostics_at']);
        });
    }
};
