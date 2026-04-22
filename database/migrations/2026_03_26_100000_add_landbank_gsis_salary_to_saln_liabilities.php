<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::statement("ALTER TABLE saln_liabilities MODIFY COLUMN nature ENUM(
            'housing_loan',
            'car_loan',
            'personal_loan',
            'credit_card',
            'gsis_policy_loan',
            'gsis_salary_loan',
            'pagibig_loan',
            'sss_loan',
            'landbank_salary_loan',
            'business_loan',
            'others'
        ) NOT NULL DEFAULT 'others'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::statement("ALTER TABLE saln_liabilities MODIFY COLUMN nature ENUM(
            'housing_loan',
            'car_loan',
            'personal_loan',
            'credit_card',
            'gsis_policy_loan',
            'pagibig_loan',
            'sss_loan',
            'business_loan',
            'others'
        ) NOT NULL DEFAULT 'others'");
    }
};
