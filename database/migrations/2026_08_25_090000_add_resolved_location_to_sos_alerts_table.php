<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sos_alerts', function (Blueprint $table) {
            $table->string('resolved_location_type')->nullable()->after('geofence_zone_id');
            $table->string('resolved_location_label')->nullable()->after('resolved_location_type');
            $table->string('resolved_building')->nullable()->after('resolved_location_label');
            $table->string('resolved_room')->nullable()->after('resolved_building');
            $table->string('resolved_source')->nullable()->after('resolved_room');
        });
    }

    public function down(): void
    {
        Schema::table('sos_alerts', function (Blueprint $table) {
            $table->dropColumn([
                'resolved_location_type', 'resolved_location_label',
                'resolved_building', 'resolved_room', 'resolved_source',
            ]);
        });
    }
};
