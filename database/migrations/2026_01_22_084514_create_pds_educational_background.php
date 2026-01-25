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
        Schema::create('pds_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_id')->constrained('pds')->onDelete('cascade');
            $table->string('level'); // Elementary, Highschool, College, etc
            $table->string('school_name');
            $table->string('degree')->nullable();
            $table->date('from')->nullable();
            $table->date('to')->nullable();
            $table->string('highest_level')->nullable();
            $table->year('year_graduated')->nullable();
            $table->string('honors')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_educational_background');
    }
};
