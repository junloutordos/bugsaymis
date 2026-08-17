<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_opcrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_period_id')->constrained('spms_fiscal_periods')->cascadeOnDelete();
            $table->foreignId('ratee_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('Draft');
            $table->foreignId('weight_profile_id')->nullable()->constrained('spms_weight_profiles')->nullOnDelete();
            $table->decimal('rolled_up_rating', 4, 2)->nullable();
            $table->decimal('override_rating', 4, 2)->nullable();
            $table->text('override_reason')->nullable();
            $table->decimal('final_rating', 4, 2)->nullable();
            $table->string('final_adjectival')->nullable();
            $table->text('return_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_to_ed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_opcrs');
    }
};
