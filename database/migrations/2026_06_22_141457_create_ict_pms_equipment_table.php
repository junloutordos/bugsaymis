<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Production already has this table (created outside the migration
     * system at some point) - this just brings dev/fresh environments in
     * line and finally tracks it.
     */
    public function up(): void
    {
        if (Schema::hasTable('ict_pms_equipment')) {
            return;
        }

        Schema::create('ict_pms_equipment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ict_pms_id');
            $table->unsignedBigInteger('equipment_id');
            $table->timestamps();

            $table->foreign('ict_pms_id')->references('id')->on('ict_pms')->cascadeOnDelete();
            $table->foreign('equipment_id')->references('id')->on('ict_equipments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ict_pms_equipment');
    }
};
