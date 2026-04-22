<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (! Schema::hasTable('ict_equipments')) return;
        Schema::table('ict_equipments', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->after('status');
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
        });
    }

    public function down()
    {
        if (! Schema::hasTable('ict_equipments')) return;
        Schema::table('ict_equipments', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropColumn('room_id');
        });
    }

};
