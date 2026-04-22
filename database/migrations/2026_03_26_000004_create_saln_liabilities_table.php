<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SALN Liabilities Schedule.
     *
     * Corresponds to the "LIABILITIES" section of the CSC SALN form.
     * Columns: Nature of Liability, Name of Creditor, Outstanding Balance.
     */
    public function up(): void
    {
        Schema::create('saln_liabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saln_record_id')
                ->constrained('saln_records')
                ->cascadeOnDelete();

            // CSC Form: "Nature of Liability"
            $table->enum('nature', [
                'housing_loan',
                'car_loan',
                'personal_loan',
                'credit_card',
                'gsis_policy_loan',
                'pagibig_loan',
                'sss_loan',
                'business_loan',
                'others',
            ])->default('others');

            $table->string('nature_other', 150)->nullable()
                ->comment('Specify if nature = others');

            // CSC Form: "Name of Creditor"
            $table->string('creditor_name');

            // CSC Form: "Outstanding Balance"
            $table->decimal('outstanding_balance', 15, 2)->default(0);

            $table->timestamps();

            $table->index('saln_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saln_liabilities');
    }
};
