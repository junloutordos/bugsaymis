<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIctEquipmentAlertsTable extends Migration
{
    public function up()
    {
        Schema::create('ict_equipment_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('equipment_id')->nullable()->index();
            $table->string('issue');
            $table->string('severity', 20)->default('info');
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('it_job_request_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('ict_equipment_devices')->onDelete('cascade');
            $table->foreign('equipment_id')->references('id')->on('ict_equipments')->onDelete('set null');
            $table->foreign('it_job_request_id')->references('id')->on('it_job_requests')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ict_equipment_alerts');
    }
}
