<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('levelid')->nullable();
            $table->string('sectionname', 255)->nullable();
            $table->integer('adviser')->nullable();
            $table->string('permission', 3)->nullable();
            $table->string('asstadviser', 5)->nullable();
            $table->string('lifecoach', 200)->nullable();
            $table->string('classtreasurer', 200)->nullable();
            $table->string('adtechincharge', 200)->nullable();
            $table->string('classpresident', 200)->nullable();
            $table->string('do', 200)->nullable();
            $table->string('scalecoor', 200)->nullable();
            $table->integer('syid')->nullable();
            $table->string('homeroomcoor', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
