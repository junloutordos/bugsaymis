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
        Schema::create('ict_pms_equipment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ict_pms_id');
            $table->unsignedBigInteger('equipment_id');
            $table->timestamps();

            $table->foreign('ict_pms_id')->references('id')->on('ict_pms')->onDelete('cascade');
            $table->foreign('equipment_id')->references('id')->on('ict_equipments')->onDelete('cascade');
        });

    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ict_pms_equipment');
    }

};
