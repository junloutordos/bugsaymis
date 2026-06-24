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
            if (! Schema::hasColumn('ict_equipment_devices', 'last_update_details')) {
                $table->text('last_update_details')->nullable()->after('last_update_result');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            if (Schema::hasColumn('ict_equipment_devices', 'last_update_details')) {
                $table->dropColumn('last_update_details');
            }
        });
    }
};
