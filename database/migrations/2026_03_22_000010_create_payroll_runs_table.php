<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('control_no')->unique()->nullable()
                ->comment('Auto-generated: PAY-YYYY-MM-P1/P2');

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');      // 1-12
            $table->enum('period', ['1st', '2nd'])     // 1st = 1-15, 2nd = 16-EOM
                ->comment('Payroll cut-off period');

            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable();

            $table->enum('status', [
                'draft',        // Being prepared
                'processing',   // Queue job running
                'for_review',   // Computed, awaiting approval
                'approved',     // Approved by authorized officer
                'released',     // Payslips released
                'cancelled',
            ])->default('draft')->index();

            // Summary totals (populated after processing)
            $table->integer('employee_count')->default(0);
            $table->decimal('total_gross', 14, 4)->default(0);
            $table->decimal('total_deductions', 14, 4)->default(0);
            $table->decimal('total_net', 14, 4)->default(0);

            // Approval
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();

            // Queue tracking
            $table->string('job_batch_id')->nullable()
                ->comment('Laravel job batch ID for progress tracking');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['year', 'month', 'period'], 'payroll_runs_period_unique');
            $table->index(['year', 'month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
