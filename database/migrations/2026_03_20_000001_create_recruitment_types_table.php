<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('has_ranking')->default(false);
            $table->boolean('has_exam')->default(false);
            $table->boolean('has_interview')->default(false);
            $table->boolean('requires_csc_eligibility')->default(false);
            $table->boolean('requires_prc_license')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_types');
    }
};
