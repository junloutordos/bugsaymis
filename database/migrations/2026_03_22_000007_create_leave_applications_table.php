<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->string('control_no')->unique()->nullable()
                ->comment('Auto-generated: LVE-YYYY-NNNNN');

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');

            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('days_applied', 6, 2)
                ->comment('Excludes weekends/holidays; computed on filing');

            // Leave details (CSC Form 6 fields)
            $table->enum('leave_details', [
                'within_philippines', 'abroad',         // For VL
                'in_hospital',       'out_patient',     // For SL
                'master_degree',     'bar_board_review', // For SPL
                'other',
            ])->nullable();
            $table->string('leave_details_specify')->nullable()
                ->comment('Free text for "other" and location details');

            $table->text('reason')->nullable();
            $table->string('supporting_document')->nullable()
                ->comment('Path in storage/hr/leave_documents/');

            // Approval workflow — mirrors GatePass pattern
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'cancelled', 'forwarded',
            ])->default('pending')->index();

            $table->timestamp('filed_at')->useCurrent();

            // Division Chief approval
            $table->foreignId('division_chief_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('division_chief_action', ['approved', 'rejected', 'forwarded'])->nullable();
            $table->timestamp('division_chief_at')->nullable();
            $table->text('division_chief_remarks')->nullable();

            // HR/OCD final approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('approval_action', ['approved', 'rejected'])->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();

            // After approval: actual days deducted from leave_credits
            $table->decimal('days_deducted', 6, 2)->nullable();
            $table->boolean('is_without_pay')->default(false)
                ->comment('True when leave credit balance is insufficient');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['date_from', 'date_to']);
            $table->index(['user_id', 'date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
