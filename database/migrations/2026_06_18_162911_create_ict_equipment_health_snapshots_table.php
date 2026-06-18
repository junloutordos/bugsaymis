<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIctEquipmentHealthSnapshotsTable extends Migration
{
    public function up()
    {
        Schema::create('ict_equipment_health_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('device_id')->unique();
            $table->json('payload')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('ict_equipment_devices')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ict_equipment_health_snapshots');
    }
}
