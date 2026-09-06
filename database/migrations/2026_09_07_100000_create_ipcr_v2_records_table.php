<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_v2_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rating_period_id')->constrained('ipcr_rating_periods')->cascadeOnDelete();
            $table->string('status')->default('New Target');
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('target_approved_at')->nullable();
            $table->timestamp('submitted_for_rating_at')->nullable();
            $table->timestamp('submitted_rating_at')->nullable();
            $table->timestamp('submitted_for_pmtreview_at')->nullable();
            $table->timestamp('submitted_to_hr_at')->nullable();
            $table->timestamp('director_signed_at')->nullable();
            $table->text('director_signature')->nullable();
            $table->decimal('final_numeric_rating', 5, 2)->nullable();
            $table->string('final_adjectival_rating')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'rating_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_records');
    }
};
