<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarter_exam_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years');
            $table->unsignedTinyInteger('quarter');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['school_year_id', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarter_exam_windows');
    }
};
