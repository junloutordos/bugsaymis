<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->boolean('containment_exempt')->default(false)->after('risk_tier');
            $table->string('containment_status', 20)->default('none')->after('containment_exempt');
            $table->unsignedBigInteger('containment_incident_id')->nullable()->after('containment_status');
        });
    }

    public function down(): void
    {
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->dropColumn(['containment_exempt', 'containment_status', 'containment_incident_id']);
        });
    }
};
