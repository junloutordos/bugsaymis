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
            if (! Schema::hasColumn('ict_equipment_devices', 'stale_notified_at')) {
                $table->timestamp('stale_notified_at')->nullable()->after('last_checkin_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            if (Schema::hasColumn('ict_equipment_devices', 'stale_notified_at')) {
                $table->dropColumn('stale_notified_at');
            }
        });
    }
};
