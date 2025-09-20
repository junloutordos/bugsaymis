<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ict_pms', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['equipment_id']);

            // Now drop the column
            $table->dropColumn('equipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('ict_pms', function (Blueprint $table) {
            // Recreate the column
            $table->unsignedBigInteger('equipment_id')->nullable();

            // Restore foreign key (adjust table name if not "ict_equipments")
            $table->foreign('equipment_id')
                  ->references('id')
                  ->on('ict_equipments')
                  ->onDelete('cascade');
        });
    }
};
