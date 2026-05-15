<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('payroll_batches')->cascadeOnDelete();
            $table->foreignId('bonus_batch_id')->nullable()->constrained('payroll_batches')->nullOnDelete();
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->integer('excel_row_number')->nullable();
            $table->string('employee_name_raw')->nullable();
            $table->foreignId('matched_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('match_status', ['matched', 'probable', 'unmatched', 'manual'])->default('unmatched');
            $table->string('employee_no', 50)->nullable();
            $table->string('position')->nullable();
            $table->string('office_division')->nullable();

            // ── Earnings ────────────────────────────────────────────────────────
            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->decimal('salary_increase', 14, 2)->default(0);
            $table->decimal('additional_compensation', 14, 2)->default(0);
            $table->decimal('pera', 14, 2)->default(0);
            $table->decimal('sala', 14, 2)->default(0);
            $table->decimal('hazard_pay', 14, 2)->default(0);
            $table->decimal('longevity_pay', 14, 2)->default(0);
            $table->decimal('others_bonuses', 14, 2)->default(0);
            $table->decimal('gross_earnings', 14, 2)->default(0);

            // ── Deductions ──────────────────────────────────────────────────────
            $table->decimal('lawop', 14, 2)->default(0);
            $table->decimal('pvp_overpayment', 14, 2)->default(0);
            $table->decimal('gsis_contribution', 14, 2)->default(0);
            $table->decimal('bir_tax', 14, 2)->default(0);
            $table->decimal('wht_hazard', 14, 2)->default(0);
            $table->decimal('wht_cna', 14, 2)->default(0);
            $table->decimal('longevity_tax', 14, 2)->default(0);
            $table->decimal('philhealth_contribution', 14, 2)->default(0);
            $table->decimal('philhealth_differential', 14, 2)->default(0);
            $table->decimal('hdmf_contribution', 14, 2)->default(0);
            $table->decimal('hdmf_additional', 14, 2)->default(0);
            $table->decimal('gsis_salary_loan', 14, 2)->default(0);
            $table->decimal('gsis_policy_loan', 14, 2)->default(0);
            $table->decimal('gsis_consolidated_loan', 14, 2)->default(0);
            $table->decimal('gsis_emergency_loan', 14, 2)->default(0);
            $table->decimal('gsis_mpl', 14, 2)->default(0);
            $table->decimal('gsis_gfal', 14, 2)->default(0);
            $table->decimal('gsis_cpl', 14, 2)->default(0);
            $table->decimal('gsis_mpl_lite', 14, 2)->default(0);
            $table->decimal('hdmf_salary_loan', 14, 2)->default(0);
            $table->decimal('hdmf_calamity', 14, 2)->default(0);
            $table->decimal('hdmf_housing', 14, 2)->default(0);
            $table->decimal('hdmf_multipurpose', 14, 2)->default(0);
            $table->decimal('hdmf_mp2', 14, 2)->default(0);
            $table->decimal('landbank_loan', 14, 2)->default(0);
            $table->decimal('credit_union', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);

            // ── Net ─────────────────────────────────────────────────────────────
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->decimal('first_half_amount', 14, 2)->default(0);
            $table->decimal('second_half_amount', 14, 2)->default(0);

            // ── Manual / audit ──────────────────────────────────────────────────
            $table->json('adjustments_json')->nullable();
            $table->json('raw_row_json')->nullable();
            $table->timestamp('bonus_uploaded_at')->nullable();
            $table->timestamps();

            // One payslip per employee per month (NULLs are not considered equal — unmatched rows can coexist)
            $table->unique(['year', 'month', 'matched_user_id'], 'payroll_items_per_user_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
