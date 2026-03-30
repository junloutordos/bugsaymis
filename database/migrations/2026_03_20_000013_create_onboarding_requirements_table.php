<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_type_id')->constrained('recruitment_types')->cascadeOnDelete();
            $table->string('requirement_name');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('recruitment_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_requirements');
    }
};
