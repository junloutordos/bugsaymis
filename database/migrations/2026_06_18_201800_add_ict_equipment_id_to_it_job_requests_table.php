<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIctEquipmentIdToItJobRequestsTable extends Migration
{
    public function up()
    {
        // ict_equipment_id already exists in production (added outside
        // tracked migrations, like ict_equipments itself) — only add it
        // where genuinely missing, e.g. dev.
        if (Schema::hasColumn('it_job_requests', 'ict_equipment_id')) {
            return;
        }

        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('ict_equipment_id')->nullable()->after('description');
            $table->foreign('ict_equipment_id')->references('id')->on('ict_equipments')->onDelete('set null');
        });
    }

    public function down()
    {
        // Never drop this in case it predates the migration in production.
    }
}
