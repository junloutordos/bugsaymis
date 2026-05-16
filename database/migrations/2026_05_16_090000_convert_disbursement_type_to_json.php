<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add temp JSON column alongside the existing varchar
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->json('disbursement_type_json')->nullable()->after('disbursement_type');
        });

        // Wrap each existing varchar value into a JSON array: "monthly_salary" → ["monthly_salary"]
        DB::statement("UPDATE payroll_batches SET disbursement_type_json = JSON_ARRAY(disbursement_type)");

        // Drop varchar column, rename JSON column to take its place
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropColumn('disbursement_type');
        });

        DB::statement("ALTER TABLE payroll_batches CHANGE disbursement_type_json disbursement_type JSON NOT NULL");

        // Make second_half_credit_date explicitly optional (no code change needed — already nullable)
    }

    public function down(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->string('disbursement_type_old', 50)->nullable()->after('disbursement_type');
        });

        // Extract first element of the JSON array back to varchar
        DB::statement("UPDATE payroll_batches SET disbursement_type_old = JSON_UNQUOTE(JSON_EXTRACT(disbursement_type, '$[0]'))");

        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropColumn('disbursement_type');
        });

        DB::statement("ALTER TABLE payroll_batches CHANGE disbursement_type_old disbursement_type VARCHAR(50) NOT NULL DEFAULT 'monthly_salary'");
    }
};
