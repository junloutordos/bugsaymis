<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('grade');       // 1–33
            $table->unsignedTinyInteger('step');        // 1–8
            $table->decimal('monthly_rate', 12, 2);     // Monthly salary rate in PHP
            $table->string('tranche')->default('SSL V T4 2023'); // Reference label
            $table->date('effective_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['grade', 'step', 'tranche']);
            $table->index(['grade', 'step', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_grades');
    }
};
