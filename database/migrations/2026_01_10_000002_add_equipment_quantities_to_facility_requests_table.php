<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('facility_requests', 'equipment_quantities')) {
            Schema::table('facility_requests', function (Blueprint $table) {
                $table->text('equipment_quantities')->nullable()->after('equipment');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('facility_requests', 'equipment_quantities')) {
            Schema::table('facility_requests', function (Blueprint $table) {
                $table->dropColumn('equipment_quantities');
            });
        }
    }
};
