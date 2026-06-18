<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCodeToIctEquipmentAlertsTable extends Migration
{
    public function up()
    {
        Schema::table('ict_equipment_alerts', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->after('device_id');
            $table->index(['device_id', 'code', 'status']);
        });
    }

    public function down()
    {
        Schema::table('ict_equipment_alerts', function (Blueprint $table) {
            $table->dropIndex(['device_id', 'code', 'status']);
            $table->dropColumn('code');
        });
    }
}
