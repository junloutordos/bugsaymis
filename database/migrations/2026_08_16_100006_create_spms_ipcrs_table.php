<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_ipcrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->constrained('spms_fiscal_periods')->cascadeOnDelete();
            $table->unsignedBigInteger('dpcr_id')->nullable(); // FK added in Phase 2 when spms_dpcrs exists
            $table->string('status')->default('Draft Target');
            $table->foreignId('weight_profile_id')->nullable()->constrained('spms_weight_profiles')->nullOnDelete();
            $table->decimal('final_rating', 4, 2)->nullable();
            $table->string('final_adjectival')->nullable();
            $table->text('return_reason')->nullable();
            $table->timestamp('target_submitted_at')->nullable();
            $table->timestamp('target_approved_at')->nullable();
            $table->timestamp('submitted_for_rating_at')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamp('director_signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_ipcrs');
    }
};
