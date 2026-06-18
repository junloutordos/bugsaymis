<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIctEquipmentsTable extends Migration
{
    public function up()
    {
        // ict_equipments already exists in production (created outside tracked
        // migrations) — only create it where it's genuinely missing, e.g. dev.
        if (Schema::hasTable('ict_equipments')) {
            return;
        }

        Schema::create('ict_equipments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('category');
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->string('property_no')->nullable();
            $table->string('serial_no');
            $table->text('description');
            $table->date('date_acquired')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('room_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('set null');
        });
    }

    public function down()
    {
        // Never drop ict_equipments here — production's copy holds real data
        // and predates this migration; rolling back must not touch it.
    }
}
