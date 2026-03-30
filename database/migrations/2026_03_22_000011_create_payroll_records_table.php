<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Salary snapshot at time of payroll computation
            $table->unsignedTinyInteger('salary_grade');
            $table->unsignedTinyInteger('step');
            $table->decimal('monthly_salary', 12, 4)
                ->comment('Salary table value at SG/Step at time of computation');

            // Period-specific amounts
            $table->decimal('basic_pay', 12, 4)->default(0)
                ->comment('Monthly salary / 2 adjusted for absences/tardiness');

            // Attendance summary for this period
            $table->decimal('days_worked', 6, 2)->default(0);
            $table->decimal('days_absent', 6, 2)->default(0);
            $table->decimal('late_minutes', 8, 2)->default(0);
            $table->decimal('undertime_minutes', 8, 2)->default(0);
            $table->decimal('overtime_minutes', 8, 2)->default(0);

            // Deduction for absences/tardiness
            $table->decimal('tardiness_deduction', 12, 4)->default(0);
            $table->decimal('absence_deduction', 12, 4)->default(0);
            $table->decimal('lwop_deduction', 12, 4)->default(0)
                ->comment('Leave Without Pay deduction');

            // Computed totals (populated by PayrollService)
            $table->decimal('gross_pay', 12, 4)->default(0)
                ->comment('basic_pay + all allowances');
            $table->decimal('total_deductions', 12, 4)->default(0)
                ->comment('Sum of all mandatory + voluntary deductions');
            $table->decimal('net_pay', 12, 4)->default(0)
                ->comment('gross_pay - total_deductions');
            $table->decimal('withheld_tax', 12, 4)->default(0);

            $table->enum('status', ['computed', 'approved', 'released'])->default('computed');
            $table->boolean('is_on_leave_without_pay')->default(false);

            // Payslip
            $table->string('payslip_path')->nullable()
                ->comment('Generated PDF path in storage/payslips/');

            $table->timestamps();

            $table->unique(['payroll_run_id', 'user_id'], 'payroll_records_unique');
            $table->index(['user_id', 'payroll_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
    }
};
