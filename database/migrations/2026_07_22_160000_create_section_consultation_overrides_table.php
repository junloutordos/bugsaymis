<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_consultation_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('section_id');
            $table->string('day_of_week', 12);
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'day_of_week'], 'section_consultation_day_unique');
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_consultation_overrides');
    }
};
