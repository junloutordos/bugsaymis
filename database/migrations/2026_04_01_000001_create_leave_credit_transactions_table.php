<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_credit_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');

            // Transaction classification
            $table->enum('type', [
                'INITIAL',       // Opening balance entered manually / migrated from legacy records
                'ACCRUAL',       // Monthly accrual (1.25 days VL + 1.25 days SL for non-teaching, etc.)
                'DEDUCTION',     // Credits consumed by an approved leave application
                'ADJUSTMENT',    // Manual correction by HR (positive or negative)
                'RESTORATION',   // Credits restored when a leave is cancelled/rejected after deduction
                'MONETIZATION',  // Credits converted to cash
                'CARRYOVER',     // Credits carried forward from prior year
                'FORFEITURE',    // Credits forfeited at year-end beyond the allowable cap
            ]);

            // Signed amount — positive = credit added, negative = credit removed
            $table->decimal('amount', 8, 4)
                ->comment('Positive = earned/added; Negative = deducted/removed');

            // Running balance snapshot after this transaction
            $table->decimal('balance_after', 8, 4)
                ->comment('Usable balance of this leave type for this year after the transaction');

            // Optional reference to the triggering record
            $table->string('referenceable_type')->nullable()
                ->comment('Polymorphic: App\\Models\\HR\\LeaveApplication, etc.');
            $table->unsignedBigInteger('referenceable_id')->nullable();

            $table->text('remarks')->nullable()
                ->comment('Free-text note — mandatory for INITIAL, ADJUSTMENT, and FORFEITURE');

            // Who recorded this entry (null = system-generated)
            $table->foreignId('recorded_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'leave_type_id', 'year'], 'lct_user_type_year');
            $table->index(['referenceable_type', 'referenceable_id'], 'lct_referenceable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_credit_transactions');
    }
};
