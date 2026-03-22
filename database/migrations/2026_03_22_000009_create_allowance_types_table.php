<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()
                ->comment('RATA, PERA, COLA, LWOP, ACA, etc.');
            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('category', [
                'allowance',    // Additional pay (PERA, RATA, ACA, COLA)
                'deduction',    // Mandatory/voluntary deductions (GSIS, PhilHealth, Pag-IBIG, BIR)
                'bonus',        // 13th month, year-end bonus, loyalty
                'other',
            ])->default('allowance');

            $table->boolean('is_taxable')->default(false)
                ->comment('Subject to withholding tax computation');
            $table->boolean('is_mandatory')->default(false)
                ->comment('Automatically included in all payroll runs');
            $table->boolean('is_fixed_amount')->default(true)
                ->comment('False = percentage of basic salary');

            $table->decimal('default_amount', 12, 4)->default(0)
                ->comment('Default fixed amount or percentage (e.g. 0.09 for 9%)');

            // Which employment types receive this allowance
            $table->json('applicable_employment_types')->nullable();

            // Salary grade range eligibility (null = all)
            $table->unsignedTinyInteger('sg_min')->nullable();
            $table->unsignedTinyInteger('sg_max')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allowance_types');
    }
};
