<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->string('disbursement_type', 50)->default('monthly_salary')->after('batch_type');
            $table->string('label', 150)->nullable()->after('disbursement_type');
            $table->date('first_half_credit_date')->nullable()->after('label');
            $table->date('second_half_credit_date')->nullable()->after('first_half_credit_date');
            $table->date('credit_date')->nullable()->after('second_half_credit_date');
        });

        // Widen send_type to varchar so new disbursement types can be stored
        DB::statement("ALTER TABLE payroll_emails MODIFY COLUMN send_type VARCHAR(50) NOT NULL DEFAULT 'initial'");
    }

    public function down(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropColumn(['disbursement_type', 'label', 'first_half_credit_date', 'second_half_credit_date', 'credit_date']);
        });

        DB::statement("ALTER TABLE payroll_emails MODIFY COLUMN send_type ENUM('initial','bonus_update','resend') NOT NULL DEFAULT 'initial'");
    }
};
