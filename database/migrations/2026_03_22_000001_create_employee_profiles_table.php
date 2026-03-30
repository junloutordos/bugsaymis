<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR Module — Phase 1
 * Extends the existing `users` table with payroll-critical employee fields.
 * Safe: additive only, no existing column changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Appointment & Classification
            $table->enum('employment_type', [
                'permanent', 'casual', 'contractual', 'coterminous',
                'substitute', 'job_order', 'consultant',
            ])->default('permanent');
            $table->string('plantilla_item_no')->nullable()->index();
            $table->date('appointment_date')->nullable();
            $table->date('original_appointment_date')->nullable();
            $table->date('last_promotion_date')->nullable();
            $table->date('step_increment_date')->nullable();
            $table->string('civil_service_eligibility')->nullable();
            $table->string('eligibility_rating')->nullable();

            // Salary (links to existing salary_grades table)
            $table->unsignedTinyInteger('salary_grade')->nullable()->index();
            $table->unsignedTinyInteger('salary_step')->default(1);
            $table->decimal('monthly_rate', 12, 2)->nullable()
                ->comment('Snapshot from salary_grades; recalculated on step change');

            // Banking
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('atm_account_no')->nullable();

            // Government IDs (may overlap with PDS — kept here for payroll speed)
            $table->string('gsis_bp_no')->nullable()->index();
            $table->string('gsis_policy_no')->nullable();
            $table->string('philhealth_no')->nullable()->index();
            $table->string('pagibig_mid_no')->nullable()->index();
            $table->string('tin_no')->nullable()->index();
            $table->string('sss_no')->nullable();

            // Payroll flags
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_on_plantilla')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['salary_grade', 'salary_step']);
            $table->index(['employment_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
