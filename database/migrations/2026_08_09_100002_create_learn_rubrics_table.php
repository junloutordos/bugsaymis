<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_assignment_id')->unique()->constrained('learn_assignments')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_rubrics');
    }
};
