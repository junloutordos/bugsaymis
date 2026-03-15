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
        Schema::create('section_students', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('studentid')->nullable();
            $table->integer('levelid')->nullable();
            $table->integer('sectionid')->nullable();
            $table->integer('syid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('section_students');
    }
};
