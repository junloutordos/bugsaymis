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
        Schema::table('performance_indicators', function (Blueprint $table) {
            // First drop the foreign key
            $table->dropForeign(['division_id']);
            // Then drop the column
            $table->dropColumn('division_id');
        });
    }

    public function down()
    {
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->unsignedBigInteger('division_id')->nullable();
            $table->foreign('division_id')->references('id')->on('divisions')->onDelete('cascade');
        });
    }


};
