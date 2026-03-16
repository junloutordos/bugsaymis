<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ict_pms_equipment', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('equipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('ict_pms_equipment', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
