<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('ict_equipment_id')
                  ->nullable()
                  ->after('assignedto');

            $table->foreign('ict_equipment_id')
                  ->references('id')
                  ->on('ict_equipments')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropForeign(['ict_equipment_id']);
            $table->dropColumn('ict_equipment_id');
        });
    }
};

