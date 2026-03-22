<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');

            $table->decimal('earned', 8, 4)->default(0)
                ->comment('Credits earned this year (accrued monthly for VL/SL = 1.25/mo)');
            $table->decimal('carried_over', 8, 4)->default(0)
                ->comment('Balance carried over from previous year');
            $table->decimal('used', 8, 4)->default(0)
                ->comment('Approved leave days consumed this year');
            $table->decimal('forfeited', 8, 4)->default(0)
                ->comment('Credits forfeited at year-end beyond cap');
            $table->decimal('monetized', 8, 4)->default(0)
                ->comment('Credits converted to cash');

            $table->decimal('balance', 8, 4)->storedAs('earned + carried_over - used - forfeited - monetized')
                ->comment('Current usable balance (computed column)');

            $table->timestamps();

            $table->unique(['user_id', 'leave_type_id', 'year'], 'leave_credits_unique');
            $table->index(['user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_credits');
    }
};
