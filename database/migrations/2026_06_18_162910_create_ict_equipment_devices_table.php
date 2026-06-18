<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIctEquipmentDevicesTable extends Migration
{
    public function up()
    {
        Schema::create('ict_equipment_devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('equipment_id')->nullable()->index();
            $table->string('hostname')->nullable();
            $table->string('mac_address', 20)->nullable();
            $table->string('os_version')->nullable();
            $table->string('agent_version', 20)->nullable();
            $table->timestamp('last_checkin_at')->nullable();
            $table->timestamps();

            $table->foreign('equipment_id')->references('id')->on('ict_equipments')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ict_equipment_devices');
    }
}
