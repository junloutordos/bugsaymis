<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add status to ict_pms_dates
        Schema::table('ict_pms_dates', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('schedule_date');
        });

        // Drop status from ict_pms_equipment
        Schema::table('ict_pms_equipment', function (Blueprint $table) {
            if (Schema::hasColumn('ict_pms_equipment', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    public function down()
    {
        // Remove status from ict_pms_dates
        Schema::table('ict_pms_dates', function (Blueprint $table) {
            if (Schema::hasColumn('ict_pms_dates', 'status')) {
                $table->dropColumn('status');
            }
        });

        // Re-add status to ict_pms_equipment
        Schema::table('ict_pms_equipment', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('equipment_id');
        });
    }
};
