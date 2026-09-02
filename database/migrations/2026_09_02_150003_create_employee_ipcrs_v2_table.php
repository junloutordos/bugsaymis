<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_ipcrs_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rating_period_id')->constrained('ipcr_rating_periods_v2')->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('New Target');
            $table->text('remarks')->nullable();
            $table->timestamp('target_approved_at')->nullable();
            $table->timestamp('submitted_for_rating_at')->nullable();
            $table->timestamp('submitted_rating_at')->nullable();
            $table->decimal('final_numeric_rating', 5, 2)->nullable();
            $table->string('final_adjectival_rating', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ipcrs_v2');
    }
};
